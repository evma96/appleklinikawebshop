<?php
/**
 * Plugin Name: Nero AI Product Image Optimizer for WooCommerce
 * Description: Optimize WooCommerce product images with AI-powered background removal and background change.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Author: Nero AI
 * Author URI: https://ai.nero.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nero-ai-product-image-optimizer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_NERO_AI_IMAGE_OPTIMIZER_VERSION', '1.0.0');
define('WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_FILE', __FILE__);
define('WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_FILE, true);
    }
});

/**
 * Main plugin class
 */
final class WC_Nero_AI_Image_Optimizer
{

    /**
     * Plugin instance
     *
     * @var WC_Nero_AI_Image_Optimizer
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return WC_Nero_AI_Image_Optimizer
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
        // Check dependencies
        add_action('admin_init', array($this, 'check_dependencies'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Set HTTP request timeout
        add_filter('http_request_timeout', fn() => 60);

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    /**
     * Check plugin dependencies
     */
    public function check_dependencies()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            deactivate_plugins(WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_BASENAME);
            if (is_admin() && current_user_can('activate_plugins')) {
                wp_safe_redirect(admin_url('plugins.php'));
                exit;
            }
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice()
    {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Nero AI Product Image Optimizer for WooCommerce requires WooCommerce to be installed and activated.', 'nero-ai-product-image-optimizer'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Load dependencies
        $this->load_dependencies();

        // Initialize classes
        $this->init_classes();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        $files = array(
            'includes/class-wc-nero-ai-image-optimizer-admin.php',
            'includes/class-wc-nero-ai-image-optimizer-ajax.php',
            'includes/class-wc-nero-ai-image-optimizer-api.php'
        );

        foreach ($files as $file) {
            $file_path = WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize plugin classes
     */
    private function init_classes()
    {
        try {
            if (class_exists('WC_Nero_AI_Image_Optimizer_Admin')) {
                WC_Nero_AI_Image_Optimizer_Admin::get_instance();
            }

            if (class_exists('WC_Nero_AI_Image_Optimizer_AJAX')) {
                WC_Nero_AI_Image_Optimizer_AJAX::get_instance();
            }

            if (class_exists('WC_Nero_AI_Image_Optimizer_API')) {
                WC_Nero_AI_Image_Optimizer_API::get_instance();
            }

        } catch (Exception $e) {
            // Do nothing
        }
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Add default options
        add_option('wc_nero_ai_image_optimizer_api_key', '');
    }
}

// Initialize plugin
WC_Nero_AI_Image_Optimizer::get_instance();
