<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Isolated rendering stubs; use the installed WordPress HTML sanitizer, but do
// not bootstrap WordPress, its database, plugins, credentials, or network.
function apply_filters(string $name, mixed $value, mixed ...$args): mixed { return $value; }
function wp_allowed_protocols(): array { return ['http', 'https']; }
function is_account_page(): bool { return $GLOBALS['view_test_account']; }
function is_user_logged_in(): bool { return $GLOBALS['view_test_user'] > 0; }
function get_current_user_id(): int { return $GLOBALS['view_test_user']; }
function esc_html(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
function esc_attr(mixed $value): string { return esc_html($value); }
function esc_url(string $value): string { return esc_html($value); }
function admin_url(string $path): string { return 'http://localhost/wp-admin/' . $path; }
function wp_nonce_field(string $action): void { echo '<input name="_wpnonce" value="local-test">'; }

require_once dirname(__DIR__, 4) . '/wp-includes/kses.php';
require_once dirname(__DIR__) . '/src/Domain/DeliveryMode.php';
require_once dirname(__DIR__) . '/src/Domain/FulfilmentWorkflow.php';
require_once dirname(__DIR__) . '/src/Domain/OrderQueueQuery.php';
require_once dirname(__DIR__) . '/src/Infrastructure/WooOrderBackOfficeRepository.php';
require_once dirname(__DIR__) . '/src/Interfaces/BackOfficeRouter.php';

use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
use Appleklinika\BackOffice\Interfaces\BackOfficeRouter;

final class WC_Order
{
    public string $status = 'processing';
    public string $payment = 'bacs';
    public array $meta = [
        '_appleklinika_backoffice_delivery_mode' => 'pickup',
        '_appleklinika_backoffice_state' => 'problem',
        '_appleklinika_backoffice_history' => [[
            'from' => 'preparation', 'to' => 'ready_for_pickup',
            'action' => 'prepare_pickup', 'user' => 'INTERNAL EMPLOYEE',
            'at' => '2026-09-10 10:00:00', 'note' => 'INTERNAL SECRET',
        ]],
    ];
    public function get_meta(string $key, bool $single = true): mixed { return $this->meta[$key] ?? ''; }
    public function get_id(): int { return 123; }
    public function get_user_id(): int { return 7; }
    public function get_status(): string { return $this->status; }
    public function has_status(string $status): bool { return $this->status === $status; }
    public function is_paid(): bool { return in_array($this->status, ['processing', 'completed'], true); }
    public function get_payment_method(): string { return $this->payment; }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (! $condition) { throw new RuntimeException($message); }
};
$router = new BackOfficeRouter(new WooOrderBackOfficeRepository());
$render = static function (string $method, mixed ...$arguments) use ($router): string {
    ob_start();
    try {
        (new ReflectionMethod($router, $method))->invoke($router, ...$arguments);
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};

$address = BackOfficeRouter::addressHtml('Kovács &amp; Társa<br/>Budapest<br>Fő utca 1');
$assert(str_contains($address, '<br') && ! str_contains($address, '&lt;br'), 'WooCommerce address line breaks remain HTML, not literal text.');
$assert(str_contains($address, '&amp;') && ! str_contains($address, '&amp;amp;'), 'Already escaped customer text is not escaped twice.');
$unsafe = BackOfficeRouter::addressHtml('Név<br onclick="alert(1)"><img src=x onerror=alert(1)><a href="https://evil.example">Cím</a>');
$assert(! str_contains($unsafe, 'onclick') && ! str_contains($unsafe, '<img') && ! str_contains($unsafe, '<a'), 'Address output permits only attribute-free line breaks.');
$assert(BackOfficeRouter::addressHtml(' ') === '—', 'Missing addresses have an explicit empty state.');

$GLOBALS['view_test_account'] = true;
$GLOBALS['view_test_user'] = 7;
$order = new WC_Order();
$html = $render('renderCustomerProgress', $order);
$assert(str_contains($html, 'Átvételre előkészítve'), 'Problem progress stays at the last safe pickup stage.');
$assert(! str_contains($html, 'INTERNAL') && ! str_contains($html, 'Problém') && ! str_contains($html, 'GLS'), 'Customer output never exposes employee, note, problem, or the wrong delivery branch.');
$GLOBALS['view_test_user'] = 8;
$assert($render('renderCustomerProgress', $order) === '', 'Another customer cannot see this progress.');
$GLOBALS['view_test_user'] = 0;
$assert($render('renderCustomerProgress', $order) === '', 'Anonymous visitors cannot see this progress.');
$GLOBALS['view_test_user'] = 7;
$GLOBALS['view_test_account'] = false;
$assert($render('renderCustomerProgress', $order) === '', 'Public order hooks outside My Account do not render private progress.');

$context = ['queue' => 'new', 's' => 'Kovács', 'search_type' => 'customer', 'queue_page' => 3];
$order->meta[FulfilmentWorkflow::META_KEY] = 'preparation';
$actions = $render('renderActions', $order, 'preparation', 'pickup', $context);
$assert(str_contains($actions, 'value="prepare_pickup"') && ! str_contains($actions, 'create_label'), 'Pickup exposes only its real next action.');
foreach ($context as $key => $value) {
    $assert(str_contains($actions, 'name="' . $key . '" value="' . $value . '"'), 'Action forms retain ' . $key . '.');
}
$order->status = 'completed';
$actions = $render('renderActions', $order, 'new', 'pickup', []);
$assert(str_contains($actions, 'lezárult') && ! str_contains($actions, '<form'), 'A WooCommerce-completed order is not presented as actionable NEW.');
$order->status = 'pending';
$actions = $render('renderActions', $order, 'new', 'pickup', []);
$assert(str_contains($actions, 'fizetés ellenőrzése') && ! str_contains($actions, '<form'), 'Unpaid orders display the actual block without an enabled mutation.');
$order->status = 'processing';
$order->meta['_appleklinika_backoffice_delivery_mode'] = 'gls';
$actions = $render('renderActions', $order, 'ready_for_shipping', 'gls', []);
$assert(str_contains($actions, 'GLS kapcsolat nincs konfigurálva') && ! str_contains($actions, 'value="create_label"'), 'Unavailable provider does not appear as a functional label button.');
$order->payment = 'cod';
$payment = new ReflectionMethod($router, 'paymentLabel');
$assert($payment->invoke($router, $order) === 'Utánvét', 'COD processing never claims that cash was collected.');
$order->payment = 'bacs';
$assert($payment->invoke($router, $order) === 'Fizetés rendben', 'Existing non-COD paid wording remains intact.');
$order->status = 'refunded';
$assert($payment->invoke($router, $order) === 'Visszatérítve', 'Refunded orders are not mislabeled as cancelled.');

echo "Back Office rendered views passed: {$assertions} assertions.\n";
