<?php
/**
 * WooCommerce Nero AI Image Optimizer - API Handler Class
 *
 * @package WooCommerce_Product_Image_Optimizer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Handler Class
 */
class WC_Nero_AI_Image_Optimizer_API
{

    /**
     * Instance
     *
     * @var WC_Nero_AI_Image_Optimizer_API
     */
    private static $instance = null;

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url = 'https://api.nero.com/biz/api';

    /**
     * Get instance
     *
     * @return WC_Nero_AI_Image_Optimizer_API
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
        // No initialization needed for API class
    }

    /**
     * Ensure WP_Filesystem is initialized and return the instance
     *
     * @return WP_Filesystem_Base|WP_Error
     */
    private function get_filesystem()
    {
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (empty($wp_filesystem)) {
            WP_Filesystem();
        }
        if (!empty($wp_filesystem)) {
            return $wp_filesystem;
        }
        return new WP_Error('fs_unavailable', 'Filesystem API is not available.');
    }

    /**
     * Create background removal task
     *
     * @param string $image_url Image URL.
     * @param string $api_key API key.
     * @return string|WP_Error
     */
    public function create_bg_removal_task($image_url, $api_key)
    {
        $url = $this->api_base_url . '/task';

        $payload = [
            "type" => "BackgroundRemover",
            "body" => [
                "image" => $image_url,
                "action" => "auto"
            ],
            "info" => [
                "trace_id" => "WooC"
            ]
        ];

        // Prepare request parameters for logging
        $request_params = array(
            'url' => $url,
            'payload' => $payload,
            'image_url' => basename($image_url)
        );

        $is_local_file = file_exists($image_url);
        $headers_json = [
            "x-neroai-api-key" => $api_key,
            "Content-Type" => "application/json"
        ];

        $max_retries = 3;
        $retry_delay = 1; // seconds

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            if ($is_local_file) {
                // Determine MIME type
                $ext = strtolower(pathinfo($image_url, PATHINFO_EXTENSION));
                $mime_map = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'jpe' => 'image/jpeg',
                    'jif' => 'image/jpeg',
                    'jfif' => 'image/jpeg',
                    'jfi' => 'image/jpeg',
                    'png' => 'image/png',
                    'bmp' => 'image/bmp',
                    'webp' => 'image/webp',
                ];
                $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'application/octet-stream';

                // Read file via WP_Filesystem
                $fs = $this->get_filesystem();
                if (is_wp_error($fs)) {
                    return $fs;
                }
                $file_bits = $fs->get_contents($image_url);
                if ($file_bits === false || $file_bits === null) {
                    return new WP_Error('file_read_error', 'Failed to read image file.');
                }
                $boundary = wp_generate_password(24, false);
                $filename = basename($image_url);

                // Manual multipart/form-data body
                $multipart_body = "--$boundary\r\n";
                $multipart_body .= "Content-Disposition: form-data; name=\"payload\"\r\n\r\n";
                $multipart_body .= json_encode($payload) . "\r\n";

                $multipart_body .= "--$boundary\r\n";
                $multipart_body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\n";
                $multipart_body .= "Content-Type: $mime\r\n\r\n";
                $multipart_body .= $file_bits . "\r\n";
                $multipart_body .= "--$boundary--\r\n";

                $headers = [
                    'Content-Type' => "multipart/form-data; boundary=$boundary",
                    'x-neroai-api-key' => $api_key,
                ];

                $response = wp_remote_post($url, [
                    'headers' => $headers,
                    'body' => $multipart_body,
                    'timeout' => 60,
                ]);
            } else {
                // JSON path
                $response = wp_remote_post($url, [
                    'headers' => $headers_json,
                    'timeout' => 60,
                    'body' => json_encode($payload),
                ]);
            }

            if (is_wp_error($response)) {
                if ($attempt >= $max_retries) {
                    return $response;
                }
                sleep($retry_delay);
                $retry_delay = min(8, $retry_delay * 2);
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // API returned a structured error
            if (is_array($data) && isset($data['code']) && (int) $data['code'] !== 0) {
                $api_code = (int) $data['code'];
                // Retry on WriteConflict (112)
                if ($api_code === 112 && $attempt < $max_retries) {
                    sleep($retry_delay);
                    $retry_delay = min(8, $retry_delay * 2);
                    continue;
                }
                $message = 'API Error ' . $api_code . ': ' . (isset($data['msg']) ? $data['msg'] : 'Unknown error');
                return new WP_Error('nero_api_error', $message, array('api_code' => $api_code));
            }

            if (empty($data) || !isset($data['data']['task_id'])) {
                // Fallback: try to extract API error from response body
                $body_arr = json_decode($body, true);
                if (is_array($body_arr) && isset($body_arr['code']) && (int) $body_arr['code'] !== 0) {
                    $msg = isset($body_arr['msg']) ? $body_arr['msg'] : 'API error';
                    return new WP_Error('nero_api_error', $msg, array('api_code' => (int) $body_arr['code']));
                }
                return new WP_Error('task_creation_failed', 'Failed to create background removal task.');
            }

            $task_id = $data['data']['task_id'];
            return $task_id;
        }

        return new WP_Error('task_creation_failed', 'Failed to create background removal task after retries.');
    }

    /**
     * Create background change task
     *
     * @param string $image_url Image URL.
     * @param string $background_url Background URL.
     * @param string $api_key API key.
     * @return string|WP_Error
     */
    public function create_bg_change_task($image_url, $background_url, $api_key)
    {
        $url = $this->api_base_url . '/task';

        $is_local_file = file_exists($image_url);

        // For JSON path, normalize local path to public URL
        $image_param = $image_url;
        if (!$is_local_file && !preg_match('/^https?:\/\//i', (string) $image_url)) {
            $uploads = wp_upload_dir();
            $basedir = isset($uploads['basedir']) ? wp_normalize_path($uploads['basedir']) : '';
            $baseurl = isset($uploads['baseurl']) ? $uploads['baseurl'] : '';
            $norm = wp_normalize_path($image_url);
            if ($basedir && strpos($norm, $basedir) === 0) {
                $rel = ltrim(substr($norm, strlen($basedir)), '/');
                $image_param = trailingslashit($baseurl) . str_replace('\\', '/', $rel);
            }
        }

        $payload = [
            "type" => "BackgroundChanger",
            "body" => [
                "image" => $is_local_file ? $image_url : $image_param,
                "background" => $background_url,
            ],
            "info" => [
                "trace_id" => "WooC"
            ]
        ];

        // Prepare request parameters for logging
        $request_params = array(
            'url' => $url,
            'payload' => $payload,
            'image_url' => basename($image_url),
            'background_url' => $background_url
        );

        $max_retries = 3;
        $retry_delay = 1; // seconds

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            if ($is_local_file) {
                // multipart/form-data: attach the foreground image file
                $ext = strtolower(pathinfo($image_url, PATHINFO_EXTENSION));
                $mime_map = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'jpe' => 'image/jpeg',
                    'jif' => 'image/jpeg',
                    'jfif' => 'image/jpeg',
                    'jfi' => 'image/jpeg',
                    'png' => 'image/png',
                    'bmp' => 'image/bmp',
                    'webp' => 'image/webp',
                ];
                $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'application/octet-stream';

                $fs = $this->get_filesystem();
                if (is_wp_error($fs)) {
                    return $fs;
                }
                $file_bits = $fs->get_contents($image_url);
                if ($file_bits === false || $file_bits === null) {
                    return new WP_Error('file_read_error', 'Failed to read image file.');
                }
                $boundary = wp_generate_password(24, false);
                $filename = basename($image_url);

                // Resolve background to file bytes (keep existing logic)
                $bg_filename = 'background';
                $bg_mime = 'application/octet-stream';
                $bg_bits = '';
                $bg_path = '';
                if (file_exists($background_url)) {
                    $bg_path = $background_url;
                } else {
                    $uploads = wp_upload_dir();
                    $baseurl = isset($uploads['baseurl']) ? trailingslashit($uploads['baseurl']) : '';
                    $basedir = isset($uploads['basedir']) ? trailingslashit($uploads['basedir']) : '';
                    if ($baseurl && strpos($background_url, $baseurl) === 0) {
                        $rel = ltrim(substr($background_url, strlen($baseurl)), '/');
                        $bg_path = wp_normalize_path($basedir . $rel);
                    }
                }
                if ($bg_path && file_exists($bg_path)) {
                    $bg_filename = basename($bg_path);
                    $bg_ext = strtolower(pathinfo($bg_path, PATHINFO_EXTENSION));
                    $bg_mime = isset($mime_map[$bg_ext]) ? $mime_map[$bg_ext] : 'application/octet-stream';
                    $bg_bits = $fs->get_contents($bg_path);
                    if ($bg_bits === false || $bg_bits === null) {
                        return new WP_Error('file_read_error', 'Failed to read background file.');
                    }
                } else {
                    if (preg_match('/^https?:\\/\\/i', (string) $background_url)) {
                        $resp = wp_remote_get($background_url, [
                            'timeout' => 60,
                            'headers' => [
                                'Accept' => 'image/*,*/*;q=0.8',
                            ],
                        ]);
                        if (!is_wp_error($resp)) {
                            $code = wp_remote_retrieve_response_code($resp);
                            if ($code === 200) {
                                $bg_bits = wp_remote_retrieve_body($resp);
                                $ct = wp_remote_retrieve_header($resp, 'content-type');
                                if (is_string($ct) && stripos($ct, 'image/') === 0) {
                                    $bg_mime = $ct;
                                } else {
                                    $bg_mime = 'application/octet-stream';
                                }
                                $bg_filename = basename(wp_parse_url($background_url, PHP_URL_PATH)) ?: 'background';
                            }
                        }
                    }
                    if ($bg_bits === '' || $bg_bits === null) {
                        return new WP_Error('background_required', 'Background file is required and could not be resolved.');
                    }
                }

                // Manual multipart/form-data body
                $multipart_body = "--$boundary\r\n";
                $multipart_body .= "Content-Disposition: form-data; name=\"payload\"\r\n\r\n";
                $multipart_body .= json_encode($payload) . "\r\n";

                // Foreground file
                $multipart_body .= "--$boundary\r\n";
                $multipart_body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\n";
                $multipart_body .= "Content-Type: $mime\r\n\r\n";
                $multipart_body .= $file_bits . "\r\n";

                // Background file
                $multipart_body .= "--$boundary\r\n";
                $multipart_body .= "Content-Disposition: form-data; name=\"background\"; filename=\"$bg_filename\"\r\n";
                $multipart_body .= "Content-Type: $bg_mime\r\n\r\n";
                $multipart_body .= $bg_bits . "\r\n";

                $multipart_body .= "--$boundary--\r\n";

                $headers = [
                    'Content-Type' => "multipart/form-data; boundary=$boundary",
                    'x-neroai-api-key' => $api_key,
                ];

                $response = wp_remote_post($url, [
                    'headers' => $headers,
                    'body' => $multipart_body,
                    'timeout' => 60,
                ]);
            } else {
                // JSON path
                $headers = [
                    "x-neroai-api-key" => $api_key,
                    "Content-Type" => "application/json"
                ];

                $response = wp_remote_post($url, [
                    'headers' => $headers,
                    'timeout' => 60,
                    'body' => json_encode($payload),
                ]);
            }

            if (is_wp_error($response)) {
                if ($attempt >= $max_retries) {
                    return $response;
                }
                sleep($retry_delay);
                $retry_delay = min(8, $retry_delay * 2);
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // API returned a structured error
            if (is_array($data) && isset($data['code']) && (int) $data['code'] !== 0) {
                $api_code = (int) $data['code'];
                // Retry on WriteConflict (112)
                if ($api_code === 112 && $attempt < $max_retries) { // WriteConflict
                    sleep($retry_delay);
                    $retry_delay = min(8, $retry_delay * 2);
                    continue;
                }
                $message = 'API Error ' . $api_code . ': ' . (isset($data['msg']) ? $data['msg'] : 'Unknown error');
                return new WP_Error('nero_api_error', $message, array('api_code' => $api_code));
            }

            if (empty($data) || !isset($data['data']['task_id'])) {
                // Fallback: try to extract API error from response body
                $body_arr = json_decode($body, true);
                if (is_array($body_arr) && isset($body_arr['code']) && (int) $body_arr['code'] !== 0) {
                    $msg = isset($body_arr['msg']) ? $body_arr['msg'] : 'API error';
                    return new WP_Error('nero_api_error', $msg, array('api_code' => (int) $body_arr['code']));
                }
                return new WP_Error('task_creation_failed', 'Failed to create background change task.');
            }

            $task_id = $data['data']['task_id'];
            return $task_id;
        }

        return new WP_Error('task_creation_failed', 'Failed to create background change task after retries.');
    }



    /**
     * Batch query tasks
     *
     * @param array $task_ids Array of task IDs.
     * @param string $api_key API key.
     * @return array|WP_Error
     */
    public function batch_query_tasks($task_ids, $api_key)
    {
        if (empty($task_ids) || !is_array($task_ids)) {
            return new WP_Error('invalid_task_ids', 'Invalid task IDs provided.');
        }

        if (empty($api_key)) {
            return new WP_Error('invalid_api_key', 'Invalid API key provided.');
        }

        // Limit the number of tasks to 10 per request
        $max_tasks_per_request = 10;
        if (count($task_ids) > $max_tasks_per_request) {
            $task_ids = array_slice($task_ids, 0, $max_tasks_per_request);
        }

        // Build URL with comma-separated task IDs
        $url = $this->api_base_url . '/tasks?task_ids=' . implode('%2C', array_map('urlencode', $task_ids));

        $request_params = array(
            'task_ids' => $task_ids,
            'url' => $url
        );

        $headers = [
            "x-neroai-api-key" => $api_key,
            "Content-Type" => "application/json"
        ];

        // Retry logic for batch query
        $max_retries = 3;
        $retry_delay = 1; // seconds

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_get($url, [
                'headers' => $headers,
                'timeout' => 90, // Increased timeout to 1.5 minutes
                'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ]);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();

                // If it's the last attempt, return the error
                if ($attempt >= $max_retries) {
                    return $response;
                }

                // If it's an SSL timeout error, wait longer before retry
                if (
                    strpos($error_message, 'SSL connection timeout') !== false ||
                    strpos($error_message, 'cURL error 28') !== false
                ) {
                    $retry_delay = 3; // Wait 3 seconds for SSL timeout errors
                }

                // Wait before retrying
                sleep($retry_delay);
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $response_code = wp_remote_retrieve_response_code($response);

            // Check if response is successful
            if ($response_code !== 200) {
                if ($attempt >= $max_retries) {
                    return new WP_Error('http_error', "HTTP response code: $response_code");
                }

                sleep($retry_delay);
                continue;
            }

            $data = json_decode($body, true);

            if (empty($data)) {
                if ($attempt >= $max_retries) {
                    return new WP_Error('invalid_response', 'Invalid response from API for batch task status.');
                }

                sleep($retry_delay);
                continue;
            }

            // Success - log and return
            return $data;
        }

        // This should never be reached, but just in case
        return new WP_Error('batch_query_failed', 'Batch query failed after all retry attempts.');
    }

    /**
     * Download processed image
     *
     * @param string $url Download URL.
     * @return string|WP_Error
     */
    private function download_processed_image($url)
    {
        $request_params = array(
            'url' => $url
        );

        // Retry logic for download
        $max_retries = 3;
        $retry_delay = 2; // seconds

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_get($url, [
                'timeout' => 120, // Increased timeout to 2 minutes
                'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'headers' => [
                    'Accept' => 'image/*,*/*;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate',
                ],
            ]);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();

                // If it's the last attempt, return the error
                if ($attempt >= $max_retries) {
                    return $response;
                }

                // If it's an SSL timeout error, wait longer before retry
                if (
                    strpos($error_message, 'SSL connection timeout') !== false ||
                    strpos($error_message, 'cURL error 28') !== false
                ) {
                    $retry_delay = 5; // Wait 5 seconds for SSL timeout errors
                }

                // Wait before retrying
                sleep($retry_delay);
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $response_code = wp_remote_retrieve_response_code($response);

            // Check if response is successful
            if ($response_code !== 200) {
                if ($attempt >= $max_retries) {
                    return new WP_Error('http_error', "HTTP response code: $response_code");
                }

                sleep($retry_delay);
                continue;
            }

            if (empty($body)) {
                if ($attempt >= $max_retries) {
                    return new WP_Error('empty_response', 'Empty response body when downloading image.');
                }

                sleep($retry_delay);
                continue;
            }

            // Success - log and return
            return $body;
        }

        // This should never be reached, but just in case
        return new WP_Error('download_failed', 'Download failed after all retry attempts.');
    }

    /**
     * Download and save processed image
     *
     * @param array $task Task data.
     * @param string $original_file_path Original file path.
     * @param string $api_key API key.
     * @return array|WP_Error
     */
    private function download_and_save_processed_image($task, $original_file_path, $api_key)
    {
        if (!isset($task['result']['output'])) {
            return new WP_Error('no_output_url', 'No output URL in task result.');
        }

        $output_url = $task['result']['output'];

        // Download the processed image
        $image_data = $this->download_processed_image($output_url);
        if (is_wp_error($image_data)) {
            return $image_data;
        }

        // Determine target save path to replace the original image
        $original_dir = dirname($original_file_path);
        $original_basename = basename($original_file_path);
        $original_name_no_ext = pathinfo($original_basename, PATHINFO_FILENAME);

        // Try to detect extension from API output URL; default to png
        $detected_ext = pathinfo(wp_parse_url($output_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $detected_ext = $detected_ext ? strtolower($detected_ext) : 'png';

        $target_filename = $original_name_no_ext . '.' . $detected_ext;
        $target_path = trailingslashit($original_dir) . $target_filename;

        // If target path differs from original, remove the original to truly "replace"
        if (realpath($original_file_path) !== false && wp_normalize_path($original_file_path) !== wp_normalize_path($target_path)) {
            wp_delete_file($original_file_path);
        }

        // Save the processed image bytes using WP_Filesystem
        $fs = $this->get_filesystem();
        if (is_wp_error($fs)) {
            return $fs;
        }
        $result = $fs->put_contents($target_path, $image_data, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : null);
        if ($result === false) {
            return new WP_Error('save_failed', 'Failed to save processed image.');
        }

        // Build public URL from uploads base
        $upload_dir = wp_upload_dir();
        $relative = str_replace(trailingslashit($upload_dir['basedir']), '', wp_normalize_path($target_path));
        $final_url = trailingslashit($upload_dir['baseurl']) . str_replace('\\', '/', $relative);

        return array(
            'file_path' => $target_path,
            'url' => $final_url
        );
    }

    /**
     * Download and save processed image
     *
     * @param array $task Task data.
     * @param string $original_file_path Original file path.
     * @param string $api_key API key.
     * @return array|WP_Error
     */
    public function download_and_save_image_public($task, $original_file_path, $api_key)
    {
        return $this->download_and_save_processed_image($task, $original_file_path, $api_key);
    }
}
