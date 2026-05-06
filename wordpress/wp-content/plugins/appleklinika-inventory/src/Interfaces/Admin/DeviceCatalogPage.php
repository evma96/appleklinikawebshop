<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Interfaces\Admin;

use Appleklinika\Inventory\Domain\DeviceCatalog\DeviceType;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;

final class DeviceCatalogPage
{
    private const NONCE_ACTION = 'appleklinika_add_device';
    private const UPDATE_NONCE_ACTION = 'appleklinika_update_device';
    private const DELETE_NONCE_ACTION = 'appleklinika_delete_device';
    private const NONCE_NAME = 'appleklinika_device_catalog_nonce';

    public function __construct(private readonly DeviceCatalogRepository $repository)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_appleklinika_add_device', [$this, 'saveDevice']);
        add_action('admin_post_appleklinika_update_device', [$this, 'updateDevice']);
        add_action('admin_post_appleklinika_delete_device', [$this, 'deleteDevice']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'Appleklinika',
            'Appleklinika',
            'manage_options',
            'appleklinika',
            [$this, 'render'],
            'dashicons-smartphone',
            56
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $catalog = $this->repository->all();

        echo '<div class="wrap">';
        echo '<h1>Appleklinika Device Catalog</h1>';
        echo '<p>Admin catalog for Apple device models and Hungarian color labels with Apple color names in parentheses.</p>';

        echo '<h2>Add device model</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="appleklinika_add_device">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<table class="form-table"><tbody>';
        $this->textField('device_name', 'Model name', 'iPhone 18 Pro');
        $this->selectField('device_type', 'Device type', DeviceType::options(), DeviceType::IPHONE);
        $this->textField('device_year', 'Year introduced', (string) gmdate('Y'));
        $this->textareaField('device_colors', 'Colors', "black=Fekete (Black)\nwhite=Feher (White)");
        echo '</tbody></table>';
        submit_button('Add device');
        echo '</form>';

        echo '<h2>Current catalog</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Year</th><th>Type</th><th>Model</th><th>Colors</th><th>Action</th></tr></thead><tbody>';

        foreach ($catalog as $device) {
            $formId = 'appleklinika-device-' . esc_attr($device['key']);
            echo '<tr>';
            echo '<td><input class="small-text" form="' . $formId . '" name="device_year" type="number" value="' . esc_attr((string) $device['year']) . '"></td>';
            echo '<td>';
            echo '<select form="' . $formId . '" name="device_type">';
            foreach (DeviceType::options() as $typeKey => $typeLabel) {
                echo '<option value="' . esc_attr($typeKey) . '"' . selected($device['type'], $typeKey, false) . '>' . esc_html($typeLabel) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '<td><input class="regular-text" form="' . $formId . '" name="device_name" type="text" value="' . esc_attr($device['name']) . '"></td>';
            echo '<td><textarea class="large-text" form="' . $formId . '" name="device_colors" rows="4">' . esc_textarea($this->formatColors($device['colors'])) . '</textarea></td>';
            echo '<td>';
            echo '<form id="' . $formId . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="appleklinika_update_device">';
            echo '<input type="hidden" name="device_key" value="' . esc_attr($device['key']) . '">';
            wp_nonce_field(self::UPDATE_NONCE_ACTION, self::NONCE_NAME);
            submit_button('Save', 'secondary small', 'submit', false);
            echo '</form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Delete this catalog entry?\');" style="margin-top: 8px;">';
            echo '<input type="hidden" name="action" value="appleklinika_delete_device">';
            echo '<input type="hidden" name="device_key" value="' . esc_attr($device['key']) . '">';
            wp_nonce_field(self::DELETE_NONCE_ACTION, self::NONCE_NAME);
            submit_button('Delete', 'delete small', 'submit', false);
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function saveDevice(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to manage the device catalog.');
        }

        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            wp_die('Invalid request.');
        }

        $name = isset($_POST['device_name']) ? sanitize_text_field(wp_unslash($_POST['device_name'])) : '';
        $type = isset($_POST['device_type']) ? sanitize_key(wp_unslash($_POST['device_type'])) : DeviceType::IPHONE;
        $year = isset($_POST['device_year']) ? (int) wp_unslash($_POST['device_year']) : (int) gmdate('Y');
        $colors = $this->parseColors(isset($_POST['device_colors']) ? (string) wp_unslash($_POST['device_colors']) : '');

        $this->repository->addDevice($name, $type, $year, $colors);

        wp_safe_redirect(admin_url('admin.php?page=appleklinika'));
        exit;
    }

    public function deleteDevice(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to manage the device catalog.');
        }

        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::DELETE_NONCE_ACTION)
        ) {
            wp_die('Invalid request.');
        }

        $key = isset($_POST['device_key']) ? sanitize_key(wp_unslash($_POST['device_key'])) : '';

        $this->repository->deleteDevice($key);

        wp_safe_redirect(admin_url('admin.php?page=appleklinika'));
        exit;
    }

    public function updateDevice(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to manage the device catalog.');
        }

        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::UPDATE_NONCE_ACTION)
        ) {
            wp_die('Invalid request.');
        }

        $key = isset($_POST['device_key']) ? sanitize_key(wp_unslash($_POST['device_key'])) : '';
        $name = isset($_POST['device_name']) ? sanitize_text_field(wp_unslash($_POST['device_name'])) : '';
        $type = isset($_POST['device_type']) ? sanitize_key(wp_unslash($_POST['device_type'])) : DeviceType::IPHONE;
        $year = isset($_POST['device_year']) ? (int) wp_unslash($_POST['device_year']) : (int) gmdate('Y');
        $colors = $this->parseColors(isset($_POST['device_colors']) ? (string) wp_unslash($_POST['device_colors']) : '');

        $this->repository->updateDevice($key, $name, $type, $year, $colors);

        wp_safe_redirect(admin_url('admin.php?page=appleklinika'));
        exit;
    }

    private function textField(string $name, string $label, string $placeholder): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input class="regular-text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '" type="text"></td></tr>';
    }

    /**
     * @param array<string, string> $options
     */
    private function selectField(string $name, string $label, array $options, string $selected): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';

        foreach ($options as $key => $value) {
            echo '<option value="' . esc_attr($key) . '"' . selected($selected, $key, false) . '>' . esc_html($value) . '</option>';
        }

        echo '</select></td></tr>';
    }

    private function textareaField(string $name, string $label, string $placeholder): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><textarea class="large-text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '" rows="5"></textarea>';
        echo '<p class="description">One color per line, format: key=Hungarian label (Apple English name).</p></td></tr>';
    }

    /**
     * @param array<string, string> $colors
     */
    private function formatColors(array $colors): string
    {
        $lines = [];

        foreach ($colors as $key => $label) {
            $lines[] = $key . '=' . $label;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private function parseColors(string $input): array
    {
        $colors = [];
        $lines = preg_split('/\r\n|\r|\n/', $input) ?: [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('=', $line, 2));

            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $colors[sanitize_key($parts[0])] = sanitize_text_field($parts[1]);
        }

        return $colors;
    }
}
