<?php
/**
 * Uninstall script for Nero AI Product Image Optimizer for WooCommerce
 *
 * @package WooCommerce_Product_Image_Optimizer
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wc_nero_ai_image_optimizer_api_key');

// Clean up processed image directories
if (function_exists('wp_upload_dir')) {
    // Load WP_Filesystem
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    global $wp_filesystem;
    WP_Filesystem();

    if (!function_exists('wc_nero_ai_uninstall_remove_directory')) {
        /**
         * Recursively remove a directory using WP_Filesystem.
         *
         * @param string                $dir           Absolute directory path.
         * @param WP_Filesystem_Base    $wp_filesystem WP Filesystem instance.
         * @return bool
         */
        function wc_nero_ai_uninstall_remove_directory($dir, $wp_filesystem)
        {
            if (!$wp_filesystem || !$wp_filesystem->exists($dir)) {
                return true;
            }

            if (!$wp_filesystem->is_dir($dir)) {
                return $wp_filesystem->delete($dir);
            }

            $entries = $wp_filesystem->dirlist($dir);
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    $entry_path = trailingslashit($dir) . $entry['name'];
                    if ($entry['type'] === 'd') {
                        wc_nero_ai_uninstall_remove_directory($entry_path, $wp_filesystem);
                    } else {
                        // Prefer WordPress helper for file deletion
                        wp_delete_file($entry_path);
                        // Fallback to WP_Filesystem in case file still exists
                        if ($wp_filesystem->exists($entry_path)) {
                            $wp_filesystem->delete($entry_path);
                        }
                    }
                }
            }

            return $wp_filesystem->rmdir($dir);
        }
    }

    $upload_dir = wp_upload_dir();
}

// Clear any cached data that has been removed
wp_cache_flush();
