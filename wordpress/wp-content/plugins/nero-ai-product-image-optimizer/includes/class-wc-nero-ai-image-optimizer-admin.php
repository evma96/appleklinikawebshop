<?php
/**
 * WooCommerce Nero AI Image Optimizer - Admin Class
 *
 * @package WooCommerce_Product_Image_Optimizer
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Handler Class
 */
class WC_Nero_AI_Image_Optimizer_Admin
{

    /**
     * Instance
     *
     * @var WC_Nero_AI_Image_Optimizer_Admin
     */
    private static $instance = null;

    /**
     * Settings hook suffix
     *
     * @var string
     */
    private $settings_hook_suffix = '';

    /**
     * Get instance
     *
     * @return WC_Nero_AI_Image_Optimizer_Admin
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
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));

        // Load scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add settings page
     */
    public function add_settings_page()
    {
        $this->settings_hook_suffix = add_submenu_page(
            'woocommerce',
            __('Nero AI Product Image Optimizer for WooCommerce', 'nero-ai-product-image-optimizer'),
            __('Nero AI Image Optimizer', 'nero-ai-product-image-optimizer'),
            'manage_options',
            'wc-nero-ai-image-optimizer-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'nero-ai-product-image-optimizer'));
        }

        // Handle API key save via standard POST (WP Option API)
        if (isset($_POST['wc_nero_ai_image_optimizer_save_api'])) {
            $nonce = isset($_POST['wc_nero_ai_image_optimizer_api_nonce']) ? sanitize_text_field(wp_unslash($_POST['wc_nero_ai_image_optimizer_api_nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'wc_nero_ai_image_optimizer_save_api_action')) {
                wp_die(esc_html__('Security check failed.', 'nero-ai-product-image-optimizer'));
            }
            $posted_key = isset($_POST['wc_nero_ai_image_optimizer_api_key']) ? sanitize_text_field(wp_unslash($_POST['wc_nero_ai_image_optimizer_api_key'])) : '';
            update_option('wc_nero_ai_image_optimizer_api_key', $posted_key);
        }

        $api_key = get_option('wc_nero_ai_image_optimizer_api_key', '');
        ?>
        <div class="wrap wc-nero-ai-image-optimizer-settings-wrap">
            <!-- Header -->
            <div class="wc-nero-ai-image-optimizer-header">
                <h1><?php esc_html_e('Nero AI Product Image Optimizer for WooCommerce', 'nero-ai-product-image-optimizer'); ?>
                </h1>
                <h2 class="wc-nero-ai-image-optimizer-tagline">
                    <?php esc_html_e('Easily batch remove and replace image backgrounds, try it now for free!', 'nero-ai-product-image-optimizer'); ?>
                </h2>
            </div>

            <!-- Notices wrapper - WooCommerce notices will be moved here -->
            <div class="wc-nero-ai-image-optimizer-notices-wrapper"></div>


            <div class="wc-nero-ai-image-optimizer-container">
                <!-- API Connection Status Section -->
                <div class="wc-nero-ai-image-optimizer-section">
                    <div class="wc-nero-ai-image-optimizer-section-header">
                        <h2 class="wc-nero-ai-image-optimizer-section-title">
                            <?php esc_html_e('API Connection Status', 'nero-ai-product-image-optimizer'); ?>
                        </h2>
                        <p class="wc-nero-ai-image-optimizer-section-description">
                            <?php esc_html_e('Enter API key to connect to the service', 'nero-ai-product-image-optimizer'); ?>
                        </p>
                    </div>
                    <div class="wc-nero-ai-image-optimizer-api-section">
                        <div class="wc-nero-ai-image-optimizer-form-group">
                            <label class="wc-nero-ai-image-optimizer-form-label"
                                for="wc_nero_ai_image_optimizer_api_key"><?php esc_html_e('API Key', 'nero-ai-product-image-optimizer'); ?></label>
                            <form method="post" class="wc-nero-ai-image-optimizer-input-group" style="position:relative;">
                                <input type="text" id="wc_nero_ai_image_optimizer_api_key"
                                    name="wc_nero_ai_image_optimizer_api_key" value="<?php echo esc_attr($api_key); ?>"
                                    placeholder="<?php esc_attr_e('Enter your API key', 'nero-ai-product-image-optimizer'); ?>"
                                    class="wc-nero-ai-image-optimizer-form-input">
                                <input type="hidden" name="wc_nero_ai_image_optimizer_save_api" value="1" />
                                <?php wp_nonce_field('wc_nero_ai_image_optimizer_save_api_action', 'wc_nero_ai_image_optimizer_api_nonce'); ?>
                                <button type="submit" id="wc-nero-ai-image-optimizer-save-api"
                                    class="wc-nero-ai-image-optimizer-btn-primary"><?php esc_html_e('Save', 'nero-ai-product-image-optimizer'); ?></button>
                            </form>
                        </div>

                        <div class="wc-nero-ai-image-optimizer-info-box">
                            <div class="wc-nero-ai-image-optimizer-info-content">
                                <div class="wc-nero-ai-image-optimizer-info-text">
                                    <p><?php esc_html_e('Visit', 'nero-ai-product-image-optimizer'); ?> <a
                                            href="https://ai.nero.com/ai-api?utm_source=WooC"
                                            target="_blank"><?php esc_html_e('this page', 'nero-ai-product-image-optimizer'); ?></a>
                                        <?php esc_html_e('to get free API key.', 'nero-ai-product-image-optimizer'); ?></p>
                                    <p id="wc-nero-ai-credits-text">
                                        <?php esc_html_e('Click "Claim 50 Credits" to get free trial Credits', 'nero-ai-product-image-optimizer'); ?>
                                    </p>
                                    <p style="display:none;">
                                        <?php esc_html_e('Remaining Credits:', 'nero-ai-product-image-optimizer'); ?> <span
                                            id="wc-nero-ai-credits-remaining"
                                            style="color: #0073aa;font-weight:500;">-</span><span
                                            style="color: #0073aa;font-weight:500;">&nbsp;<?php esc_html_e('Credits', 'nero-ai-product-image-optimizer'); ?></span>
                                    </p>
                                </div>
                                <a href="https://ai.nero.com/business/pricing?utm_source=WooC" target="_blank"
                                    class="wc-nero-ai-image-optimizer-credits-link"><?php esc_html_e('Get more Credits', 'nero-ai-product-image-optimizer'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Image Upload & Processing Section -->
                <div class="wc-nero-ai-image-optimizer-section">
                    <div class="wc-nero-ai-image-optimizer-section-header">
                        <h2 class="wc-nero-ai-image-optimizer-section-title">
                            <?php esc_html_e('Image Upload & Processing', 'nero-ai-product-image-optimizer'); ?>
                        </h2>
                        <p class="wc-nero-ai-image-optimizer-section-description">
                            <?php esc_html_e('Select images to process and configure processing options', 'nero-ai-product-image-optimizer'); ?>
                        </p>
                    </div>
                    <div class="wc-nero-ai-image-optimizer-section-content">
                        <!-- Processing Tabs -->
                        <div class="wc-nero-ai-image-optimizer-tabs">
                            <div class="wc-nero-ai-image-optimizer-tab wc-nero-ai-image-optimizer-tab-active"
                                data-tab="remove-bg"><?php esc_html_e('Remove BG', 'nero-ai-product-image-optimizer'); ?></div>
                            <div class="wc-nero-ai-image-optimizer-tab" data-tab="change-bg">
                                <?php esc_html_e('Change BG', 'nero-ai-product-image-optimizer'); ?>
                            </div>
                        </div>

                        <!-- Files Header -->
                        <div class="wc-nero-ai-image-optimizer-files-header">
                            <h3 class="wc-nero-ai-image-optimizer-files-count">
                                <?php esc_html_e('Selected Files (0)', 'nero-ai-product-image-optimizer'); ?>
                            </h3>
                            <div class="wc-nero-ai-image-optimizer-btn-group">
                                <button type="button" id="wc-nero-ai-add-background"
                                    class="wc-nero-ai-image-optimizer-btn-primary" style="display: none;">
                                    <?php esc_html_e('Add new Background', 'nero-ai-product-image-optimizer'); ?>
                                </button>
                                <button type="button" id="wc-nero-ai-image-optimizer-select-images"
                                    class="wc-nero-ai-image-optimizer-btn-primary">
                                    <?php esc_html_e('Select Images', 'nero-ai-product-image-optimizer'); ?>
                                </button>
                                <button type="button" id="wc-nero-ai-image-optimizer-clear-all"
                                    class="wc-nero-ai-image-optimizer-btn-secondary" style="display: none;">
                                    <?php esc_html_e('Start over', 'nero-ai-product-image-optimizer'); ?>
                                </button>
                                <button type="button" id="wc-nero-ai-image-optimizer-start-processing"
                                    class="wc-nero-ai-image-optimizer-btn-primary" disabled>
                                    <?php esc_html_e('Start Bulk Processing', 'nero-ai-product-image-optimizer'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Files Section -->
                        <div class="wc-nero-ai-image-optimizer-files-section">
                            <div id="wc-nero-ai-bg-warning" class="wc-nero-ai-warning" style="display:none;"></div>
                            <div class="wc-nero-ai-image-optimizer-empty-state">
                                <div class="wc-nero-ai-image-optimizer-empty-icon">📂</div>
                                <div class="wc-nero-ai-image-optimizer-empty-text">
                                    <?php esc_html_e('No images selected', 'nero-ai-product-image-optimizer'); ?>
                                </div>
                                <div class="wc-nero-ai-image-optimizer-empty-description">
                                    <?php esc_html_e('Click \'Select Images\' to add images for processing', 'nero-ai-product-image-optimizer'); ?>
                                </div>
                            </div>

                            <div class="wc-nero-ai-image-optimizer-images-grid" style="display: none;">
                                <!-- Selected images will be displayed here -->
                            </div>

                            <!-- Pagination -->
                            <div class="wc-nero-ai-image-optimizer-pagination" style="display: none;">
                                <button type="button" class="wc-nero-ai-image-optimizer-pagination-btn"
                                    id="wc-nero-ai-pagination-prev">
                                    &lt; Previous
                                </button>
                                <div class="wc-nero-ai-image-optimizer-pagination-pages">
                                    <!-- Page numbers will be generated here -->
                                </div>
                                <button type="button" class="wc-nero-ai-image-optimizer-pagination-btn"
                                    id="wc-nero-ai-pagination-next">
                                    Next &gt;
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Load scripts and styles
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts($hook)
    {
        // Check if we're on the correct page
        $is_our_page = ($hook === $this->settings_hook_suffix) ||
            (strpos($hook, 'wc-nero-ai-image-optimizer-settings') !== false);

        if (!$is_our_page) {
            return;
        }

        // Enqueue jQuery and media scripts
        wp_enqueue_script('jquery');
        wp_enqueue_media();
        wp_enqueue_script('media-views');
        wp_enqueue_script('wp-media');

        // Build script URL
        $script_url = WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_URL . 'assets/js/admin.js';
        $script_path = WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_DIR . 'assets/js/admin.js';

        // Register script
        wp_register_script(
            'wc-nero-ai-image-optimizer-admin',
            $script_url,
            array('jquery', 'media-views'),
            WC_NERO_AI_IMAGE_OPTIMIZER_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'wc-nero-ai-image-optimizer-admin',
            'wcNeroAiImageOptimizerVars',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_nero_ai_image_optimizer_nonce'),
                'i18n' => array(
                    'processing' => __('Processing...', 'nero-ai-product-image-optimizer'),
                    'success' => __('Background successfully removed!', 'nero-ai-product-image-optimizer'),
                    'error' => __('An error occurred during processing, please try again.', 'nero-ai-product-image-optimizer'),
                    'no_images' => __('No images found for processing.', 'nero-ai-product-image-optimizer'),
                    'api_saved' => __('API key saved successfully!', 'nero-ai-product-image-optimizer'),
                    'api_error' => __('Failed to save API key.', 'nero-ai-product-image-optimizer'),
                    'select_images' => __('Select Images', 'nero-ai-product-image-optimizer'),
                    'change_background' => __('Change Background', 'nero-ai-product-image-optimizer'),
                ),
            )
        );

        // Register style
        $style_url = WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_URL . 'assets/css/admin.css';

        wp_register_style(
            'wc-nero-ai-image-optimizer-admin',
            $style_url,
            array(),
            WC_NERO_AI_IMAGE_OPTIMIZER_VERSION
        );

        // Load scripts and styles
        wp_enqueue_script('wc-nero-ai-image-optimizer-admin');
        wp_enqueue_style('wc-nero-ai-image-optimizer-admin');

        // Register and enqueue settings page script (migrated from inline)
        $settings_script_url = WC_NERO_AI_IMAGE_OPTIMIZER_PLUGIN_URL . 'assets/js/settings.js';
        wp_register_script(
            'wc-nero-ai-image-optimizer-settings',
            $settings_script_url,
            array('jquery'),
            WC_NERO_AI_IMAGE_OPTIMIZER_VERSION,
            true
        );
        wp_enqueue_script('wc-nero-ai-image-optimizer-settings');
    }
}
