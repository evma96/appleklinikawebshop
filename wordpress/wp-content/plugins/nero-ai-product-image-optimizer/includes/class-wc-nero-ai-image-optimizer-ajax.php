<?php
/**
 * WooCommerce Nero AI Image Optimizer - AJAX Handler Class
 *
 * @package WooCommerce_Product_Image_Optimizer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler Class
 */
class WC_Nero_AI_Image_Optimizer_AJAX
{

    /**
     * Instance
     *
     * @var WC_Nero_AI_Image_Optimizer_AJAX
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return WC_Nero_AI_Image_Optimizer_AJAX
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Core endpoints used by the current UI
        add_action('wp_ajax_wc_nero_ai_image_optimizer_batch_query_tasks', array($this, 'batch_query_tasks'));
        add_action('wp_ajax_wc_nero_ai_image_optimizer_save_api_key', array($this, 'save_api_key'));
        add_action('wp_ajax_wc_nero_ai_process_single_image_task', array($this, 'process_single_image_task'));
        add_action('wp_ajax_wc_nero_ai_save_processed_image', array($this, 'save_processed_image'));
        add_action('wp_ajax_wc_nero_ai_download_processed_to_folder', array($this, 'download_processed_to_folder'));
        add_action('wp_ajax_wc_nero_ai_save_composed_image', array($this, 'save_composed_image'));
        add_action('wp_ajax_wc_nero_ai_download_composed_to_folder', array($this, 'download_composed_to_folder'));
    }

    /**
     * Verify AJAX request
     *
     * @param string $nonce_action Nonce action.
     * @return bool
     */
    private function verify_ajax_request($nonce_action = 'wc_nero_ai_image_optimizer_nonce')
    {
        $raw_nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($raw_nonce) || false === check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error('Invalid nonce.');
            return false;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return false;
        }

        return true;
    }

    /**
     * Safe helpers to read POST parameters after verify_ajax_request().
     * These helpers centralize unslashing and sanitization and avoid direct superglobal access.
     */
    private function post_text($key, $default = '')
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_POST
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $default;
    }

    private function post_int($key, $default = 0)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_POST
        return isset($_POST[$key]) ? absint($_POST[$key]) : $default;
    }

    private function post_url($key, $default = '')
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_POST
        return isset($_POST[$key]) ? esc_url_raw(wp_unslash($_POST[$key])) : $default;
    }

    private function post_json($key)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_POST
        $json = isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
        $data = json_decode($json, true);
        return is_array($data) ? $data : array();
    }

    /**
     * Save API key
     */
    public function save_api_key()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $api_key = $this->post_text('api_key');

        try {
            // Allow clearing API key when empty
            if (trim($api_key) === '') {
                update_option('wc_nero_ai_image_optimizer_api_key', '');

                wp_send_json_success('API key cleared.');
            }

            update_option('wc_nero_ai_image_optimizer_api_key', $api_key);

            wp_send_json_success('API key saved successfully.');

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Failed to save API key: ' . $e->getMessage()));
        }
    }


    /**
     * Creates a task for a single image and returns the task ID.
     */
    public function process_single_image_task()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $attachment_id = $this->post_int('attachment_id');
        if (empty($attachment_id)) {
            wp_send_json_error(array('message' => 'Missing attachment ID.'));
        }

        $api_key = get_option('wc_nero_ai_image_optimizer_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is not configured.'));
        }

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(array('message' => 'Image file not found.'));
        }

        $api = WC_Nero_AI_Image_Optimizer_API::get_instance();
        // Optional mode/background for BackgroundChanger
        $mode = $this->post_text('mode');
        $bg_type = $this->post_text('bg_type');
        $background_url = $this->post_url('background_url');

        try {
            if ($mode === 'change-bg' && $bg_type === 'image' && !empty($background_url)) {
                $task_id = $api->create_bg_change_task($file_path, $background_url, $api_key);
            } else {
                $task_id = $api->create_bg_removal_task($file_path, $api_key);
            }

            if (is_wp_error($task_id)) {
                $payload = array('message' => $task_id->get_error_message());
                $extra = $task_id->get_error_data();
                if (is_array($extra) && isset($extra['api_code'])) {
                    $payload['api_code'] = $extra['api_code'];
                }
                wp_send_json_error($payload);
            }

            wp_send_json_success(array('task_id' => $task_id));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Task creation failed: ' . $e->getMessage()));
        }
    }

    /**
     * Saves a processed image from a download URL.
     */
    public function save_processed_image()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $attachment_id = $this->post_int('attachment_id');
        $task = $this->post_json('task');

        if (empty($attachment_id) || empty($task)) {
            wp_send_json_error('Missing parameters.');
        }

        $original_file_path = get_attached_file($attachment_id);
        if (!$original_file_path) {
            wp_send_json_error('Original file not found.');
        }

        $api_key = get_option('wc_nero_ai_image_optimizer_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error('API key is not configured.');
        }

        $api = WC_Nero_AI_Image_Optimizer_API::get_instance();

        try {
            $result = $api->download_and_save_image_public($task, $original_file_path, $api_key);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            $this->update_attachment_metadata($attachment_id, $result['file_path']);

            // Build enriched response for UI
            $upload_dir = wp_upload_dir();
            $relative = str_replace(trailingslashit($upload_dir['basedir']), '', wp_normalize_path($result['file_path']));
            $full_url = trailingslashit($upload_dir['baseurl']) . str_replace('\\', '/', $relative);

            $meta = wp_get_attachment_metadata($attachment_id);
            $width = isset($meta['width']) ? (int) $meta['width'] : 0;
            $height = isset($meta['height']) ? (int) $meta['height'] : 0;
            $mime = get_post_mime_type($attachment_id);
            $filesize = file_exists($result['file_path']) ? (int) filesize($result['file_path']) : 0;
            $filename = basename($result['file_path']);

            $thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            $thumb_url = is_array($thumb) && !empty($thumb[0]) ? $thumb[0] : $full_url;

            $payload = array_merge($result, array(
                'attachment_id' => $attachment_id,
                'full_url' => $full_url,
                'thumb_url' => $thumb_url,
                'width' => $width,
                'height' => $height,
                'mime' => $mime,
                'filesize' => $filesize,
                'filename' => $filename,
            ));
            wp_send_json_success($payload);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Image save failed: ' . $e->getMessage()));
        }
    }

    /**
     * Update attachment metadata
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path New file path.
     */
    private function update_attachment_metadata($attachment_id, $file_path)
    {
        if (!file_exists($file_path)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $relative = str_replace(trailingslashit($upload_dir['basedir']), '', wp_normalize_path($file_path));

        // Update the attached file path stored by WordPress
        update_attached_file($attachment_id, $relative);

        // Regenerate full attachment metadata (sizes, etc.)
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }

    /**
     * Batch query task status
     */
    public function batch_query_tasks()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        // Get API key
        $api_key = get_option('wc_nero_ai_image_optimizer_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is not configured.'));
        }

        // Get task IDs from request
        $task_ids = $this->post_text('task_ids');
        if (empty($task_ids)) {
            wp_send_json_error(array('message' => 'Task IDs are required.'));
        }

        // Convert comma-separated string to array
        $task_ids_array = explode(',', $task_ids);
        $task_ids_array = array_map('trim', $task_ids_array);
        $task_ids_array = array_filter($task_ids_array); // Remove empty values

        if (empty($task_ids_array)) {
            wp_send_json_error(array('message' => 'No valid task IDs provided.'));
        }

        // Initialize API class
        if (!class_exists('WC_Nero_AI_Image_Optimizer_API')) {
            wp_send_json_error(array('message' => 'API class not found.'));
        }

        $api = WC_Nero_AI_Image_Optimizer_API::get_instance();

        try {
            // Call batch query method
            $result = $api->batch_query_tasks($task_ids_array, $api_key);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Batch query failed: ' . $e->getMessage()));
        }
    }

    /**
     * Download processed image into wp-content/nero-ai-product-image-optimizer
     */
    public function download_processed_to_folder()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $attachment_id = $this->post_int('attachment_id');
        $task = $this->post_json('task');
        $mode = $this->post_text('mode', 'remove-bg');

        if (empty($attachment_id) || empty($task)) {
            wp_send_json_error('Missing parameters.');
        }

        if (!isset($task['result']['output'])) {
            wp_send_json_error('Invalid task payload (no output URL).');
        }

        $download_url = esc_url_raw($task['result']['output']);

        $original_file_path = get_attached_file($attachment_id);
        if (!$original_file_path) {
            wp_send_json_error('Original file not found.');
        }

        try {
            // Save into the same directory as the original, with a safe, mode-based suffix
            $uploads = wp_upload_dir();
            $target_dir = trailingslashit(dirname($original_file_path));

            $original_basename = basename($original_file_path);
            $name_no_ext = pathinfo($original_basename, PATHINFO_FILENAME);
            $detected_ext = pathinfo(wp_parse_url($download_url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $detected_ext = $detected_ext ? strtolower($detected_ext) : 'png';

            $suffix = ($mode === 'change-bg') ? '-nero-ai-bg-changed' : '-nero-ai-bg-removed';
            $proposed_filename = $name_no_ext . $suffix . '.' . $detected_ext;
            // Ensure unique filename within directory
            $target_filename = wp_unique_filename($target_dir, $proposed_filename);
            $target_path = trailingslashit($target_dir) . $target_filename;

            // Download bytes
            $resp = wp_remote_get($download_url, array('timeout' => 30));
            if (is_wp_error($resp)) {
                wp_send_json_error(array('message' => $resp->get_error_message()));
            }

            $body = wp_remote_retrieve_body($resp);
            if (empty($body)) {
                wp_send_json_error('Empty response while downloading image.');
            }

            if (file_put_contents($target_path, $body) === false) {
                wp_send_json_error('Failed to save file to target folder.');
            }

            // Register the new file into Media Library
            $filetype = wp_check_filetype($target_filename, null);
            $original_attachment = get_post($attachment_id);
            $post_parent = ($original_attachment && isset($original_attachment->post_parent)) ? (int) $original_attachment->post_parent : 0;
            $attachment_post = array(
                'post_mime_type' => isset($filetype['type']) ? $filetype['type'] : 'image/' . $detected_ext,
                'post_title' => sanitize_text_field($name_no_ext . $suffix),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $post_parent,
            );
            $new_attachment_id = wp_insert_attachment($attachment_post, $target_path);
            if (!is_wp_error($new_attachment_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata($new_attachment_id, $target_path);
                if (!is_wp_error($attach_data) && !empty($attach_data)) {
                    wp_update_attachment_metadata($new_attachment_id, $attach_data);
                }
            }

            // Build URL relative to uploads base
            $relative = str_replace(trailingslashit($uploads['basedir']), '', wp_normalize_path($target_path));
            $target_url = trailingslashit($uploads['baseurl']) . str_replace('\\', '/', $relative);

            $result = array(
                'file_path' => $target_path,
                'url' => $target_url,
                'attachment_id' => !is_wp_error($new_attachment_id) ? (int) $new_attachment_id : 0,
            );

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Download failed: ' . $e->getMessage()));
        }
    }

    /**
     * Save a composed image (uploaded as file) and replace the original attachment file.
     */
    public function save_composed_image()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $attachment_id = $this->post_int('attachment_id');
        if (empty($attachment_id)) {
            wp_send_json_error('Missing attachment ID.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_FILES
        if (empty($_FILES['file']) || (isset($_FILES['file']['error']) && (int) $_FILES['file']['error'] !== UPLOAD_ERR_OK)) {
            wp_send_json_error('No uploaded file found.');
        }

        $upload_overrides = array('test_form' => false);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_FILES
        $uploaded = wp_handle_upload($_FILES['file'], $upload_overrides);
        if (!is_array($uploaded) || isset($uploaded['error'])) {
            $error_message = is_array($uploaded) && isset($uploaded['error']) ? $uploaded['error'] : 'Unknown upload error.';
            wp_send_json_error('Upload failed: ' . $error_message);
        }
        $uploaded_file_path = $uploaded['file'];

        $original_file_path = get_attached_file($attachment_id);
        if (!$original_file_path) {
            wp_send_json_error('Original file not found.');
        }

        try {
            $upload_dir = wp_upload_dir();
            $original_dir = dirname($original_file_path);
            $original_basename = basename($original_file_path);
            $name_no_ext = pathinfo($original_basename, PATHINFO_FILENAME);

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_FILES
            $safe_name = isset($_FILES['file']['name']) ? sanitize_file_name(wp_unslash($_FILES['file']['name'])) : '';
            $filetype_check = wp_check_filetype_and_ext($uploaded_file_path, $safe_name);
            $detected_ext = !empty($filetype_check['ext']) ? strtolower($filetype_check['ext']) : 'png';

            $target_filename = $name_no_ext . '.' . $detected_ext;
            $target_path = trailingslashit($original_dir) . $target_filename;

            if (!file_exists($original_dir)) {
                if (!wp_mkdir_p($original_dir)) {
                    wp_send_json_error('Failed to create directory.');
                }
            }

            if (wp_normalize_path($original_file_path) !== wp_normalize_path($target_path)) {
                wp_delete_file($original_file_path);
            }

            if (wp_normalize_path($uploaded_file_path) !== wp_normalize_path($target_path)) {
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    WP_Filesystem();
                }
                if (!$wp_filesystem->move($uploaded_file_path, $target_path, true)) {
                    if (!$wp_filesystem->copy($uploaded_file_path, $target_path, true)) {
                        wp_send_json_error('Failed to save composed image.');
                    }
                    wp_delete_file($uploaded_file_path);
                }
            }

            $this->update_attachment_metadata($attachment_id, $target_path);

            $relative = str_replace(trailingslashit($upload_dir['basedir']), '', wp_normalize_path($target_path));
            $final_url = trailingslashit($upload_dir['baseurl']) . str_replace('\\', '/', $relative);

            $result = array(
                'file_path' => $target_path,
                'url' => $final_url,
                'attachment_id' => $attachment_id,
            );

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Image save failed: ' . $e->getMessage()));
        }
    }

    /**
     * Download composed image into folder as a new attachment (uploaded as file).
     */
    public function download_composed_to_folder()
    {
        if (!$this->verify_ajax_request()) {
            return;
        }

        $attachment_id = $this->post_int('attachment_id');
        $mode = $this->post_text('mode', 'change-bg');
        if (empty($attachment_id)) {
            wp_send_json_error('Missing attachment ID.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_FILES
        if (empty($_FILES['file']) || (isset($_FILES['file']['error']) && (int) $_FILES['file']['error'] !== UPLOAD_ERR_OK)) {
            wp_send_json_error('No uploaded file provided.');
        }

        $upload_overrides = array('test_form' => false);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_FILES
        $uploaded2 = wp_handle_upload($_FILES['file'], $upload_overrides);
        if (!is_array($uploaded2) || isset($uploaded2['error'])) {
            $error_message2 = is_array($uploaded2) && isset($uploaded2['error']) ? $uploaded2['error'] : 'Unknown upload error.';
            wp_send_json_error('Upload failed: ' . $error_message2);
        }
        $uploaded_file_path2 = $uploaded2['file'];

        $original_file_path = get_attached_file($attachment_id);
        if (!$original_file_path) {
            wp_send_json_error('Original file not found.');
        }

        try {
            $uploads = wp_upload_dir();
            $target_dir = trailingslashit(dirname($original_file_path));
            if (!file_exists($target_dir)) {
                if (!wp_mkdir_p($target_dir)) {
                    wp_send_json_error('Failed to create target directory.');
                }
            }

            $original_basename = basename($original_file_path);
            $name_no_ext = pathinfo($original_basename, PATHINFO_FILENAME);

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request() before reading $_FILES
            $safe_name2 = isset($_FILES['file']['name']) ? sanitize_file_name(wp_unslash($_FILES['file']['name'])) : '';
            $filetype_check2 = wp_check_filetype_and_ext($uploaded_file_path2, $safe_name2);
            $detected_ext = !empty($filetype_check2['ext']) ? strtolower($filetype_check2['ext']) : 'png';

            $suffix = ($mode === 'change-bg') ? '-nero-ai-bg-changed' : '-nero-ai-bg-removed';
            $proposed_filename = $name_no_ext . $suffix . '.' . $detected_ext;
            $target_filename = wp_unique_filename($target_dir, $proposed_filename);
            $target_path = trailingslashit($target_dir) . $target_filename;

            if (wp_normalize_path($uploaded_file_path2) !== wp_normalize_path($target_path)) {
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    WP_Filesystem();
                }
                if (!$wp_filesystem->move($uploaded_file_path2, $target_path, true)) {
                    if (!$wp_filesystem->copy($uploaded_file_path2, $target_path, true)) {
                        wp_send_json_error('Failed to save file to target folder.');
                    }
                    wp_delete_file($uploaded_file_path2);
                }
            }

            $filetype = wp_check_filetype($target_filename, null);
            $original_attachment = get_post($attachment_id);
            $post_parent = ($original_attachment && isset($original_attachment->post_parent)) ? (int) $original_attachment->post_parent : 0;
            $attachment_post = array(
                'post_mime_type' => isset($filetype['type']) ? $filetype['type'] : 'image/' . $detected_ext,
                'post_title' => sanitize_text_field($name_no_ext . $suffix),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $post_parent,
            );
            $new_attachment_id = wp_insert_attachment($attachment_post, $target_path);
            if (!is_wp_error($new_attachment_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata($new_attachment_id, $target_path);
                if (!is_wp_error($attach_data) && !empty($attach_data)) {
                    wp_update_attachment_metadata($new_attachment_id, $attach_data);
                }
            }

            $relative = str_replace(trailingslashit($uploads['basedir']), '', wp_normalize_path($target_path));
            $target_url = trailingslashit($uploads['baseurl']) . str_replace('\\', '/', $relative);

            $result = array(
                'file_path' => $target_path,
                'url' => $target_url,
                'attachment_id' => !is_wp_error($new_attachment_id) ? (int) $new_attachment_id : 0,
            );

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Download failed: ' . $e->getMessage()));
        }
    }
}
