<?php
/** @var array $view @var string $heading */
if (! defined('ABSPATH')) { exit; }
use Appleklinika\BackOffice\Infrastructure\LifecycleEmailPresentation as Text;
echo Text::plain($heading) . "\n\n";
echo Text::plain($view['greeting']) . "\n\n" . $view['intro'] . "\n\n";
echo 'Rendelés: #' . Text::plain($view['number']) . "\n";
if ($view['date'] !== '') { echo 'Rendelés dátuma: ' . $view['date'] . "\n"; }
if ($view['accepted'] || $view['received']) {
    if ($view['expected_fulfilment'] !== '') { echo $view['expected_fulfilment'] . "\n"; }
    if ($view['payment_instructions'] !== '') { echo Text::plain($view['payment_instructions']) . "\n"; }
    echo ($view['paid'] ? 'Fizetve' : ($view['cash'] ? 'Fizetés készpénzben, átvételkor' : 'Választott fizetési mód')) . ($view['payment'] !== '' ? ' · ' . Text::plain($view['payment']) : '') . "\n\nA rendelésed\n";
    foreach ($view['items'] as $item) {
        echo Text::plain($item['name']) . ' — ' . $item['quantity'] . ' db — ' . Text::plain($item['total']) . "\n";
        foreach ($item['details'] as $detail) { echo $detail['label'] . ': ' . $detail['value'] . "\n"; }
    }
    echo "\n";
    foreach ($view['totals'] as $key => $total) {
        if ($key !== 'payment_method') { echo Text::plain($total['label']) . ' ' . Text::plain($total['value']) . "\n"; }
    }
} else {
    echo "\nCsomagkövetés\n";
    foreach ($view['tracking'] as $link) { echo 'GLS csomagszám: ' . $link['code'] . "\n" . $link['url'] . "\n\n"; }
}
echo "\n" . $view['delivery_title'] . "\n" . Text::plain($view['shipping']) . "\n";
if ($view['delivery_address'] !== '') { echo Text::plain($view['delivery_address']) . "\n"; }
echo $view['delivery_note'] . "\n";
if ($view['paid']) {
    echo "\nSzámlád a mellékletben\nA rendelés számláját PDF-ként csatoltuk ehhez a levélhez.\n";
    if ($view['invoice_number'] !== '') { echo 'Számlaszám: ' . Text::plain($view['invoice_number']) . "\n"; }
    if ($view['billing_address'] !== '') { echo "\nSzámlázási adatok\n" . Text::plain($view['billing_address']) . "\n"; }
}
if ($view['account_url'] !== '') { echo "\nRendelés megtekintése a fiókomban:\n" . $view['account_url'] . "\n"; }
echo "\nApple Klinika\nEz a levél a rendelésedhez kapcsolódó értesítés.\n";
