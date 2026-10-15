<?php
// Offline fixture only: no WordPress boot/database/provider access.
define('AK_CATALOG_PLAN_LIBRARY_ONLY', true);
require __DIR__ . '/catalog-launch-plan.php';
$count = 0;
$check = static function (bool $condition) use (&$count): void {
    if (!$condition) { throw new RuntimeException('Catalog plan regression failed.'); }
    $count++;
};
foreach ([['production', 'https://appleklinika.com'], ['staging', 'https://appleklinika.com'], ['production', 'http://localhost']] as [$environment, $home]) {
    try { ak_launch_guard($environment, $home); $check(false); }
    catch (RuntimeException $e) { $check(str_contains($e->getMessage(), 'refused')); }
}
ak_launch_guard('local', 'http://localhost:8082');
ak_launch_guard('staging', 'https://teszt.appleklinika.com');
$data = ['products' => [12, 11], 'variations' => [13], 'orders' => [21, 22], 'refunds' => [23],
    'users' => [1, 5], 'media' => [30, 31, 32, 33], 'product_media' => [30, 31], 'pages' => [7, 8], 'terms' => [4]];
$before = $data;
$plan = ak_launch_plan($data, [31, 32]);
$check($plan['exclude_from_production']['product_ids_all_statuses'] === [11, 12]);
$check($plan['exclude_from_production']['variation_ids_all_statuses'] === [13]);
$check($plan['exclude_from_production']['order_ids_all_statuses'] === [21, 22]);
$check($plan['exclude_from_production']['refund_ids_all_statuses'] === [23]);
$check($plan['exclude_from_production']['user_ids_all_roles'] === [1, 5]);
$check($plan['media']['shared_product_and_approved_site_ids_keep'] === [31]);
$check($plan['media']['approved_site_media_keep_ids'] === [31, 32]);
$check($plan['media']['unapproved_ids_do_not_export_until_reviewed'] === [30, 33]);
$check($data === $before);
try { ak_launch_plan($data, [999]); $check(false); }
catch (RuntimeException $e) { $check(str_contains($e->getMessage(), 'not found')); }
echo "$count catalog guard/ID separation/shared-media/no-mutation assertions passed; fixture only.\n";
