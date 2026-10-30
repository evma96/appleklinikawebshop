<?php
/** Explicit approved accounting configuration; never a provider operation. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,4).'/wp-load.php';
$allowed=wp_get_environment_type()==='local'||(wp_get_environment_type()==='staging'&&home_url()==='https://teszt.appleklinika.com');
if(!$allowed||($argv[1]??'')!=='--apply'||!ctype_digit($argv[2]??''))throw new RuntimeException('LOCAL/TEST only: --apply <marked QA iPhone ID>');
echo json_encode((new Appleklinika\Inventory\Infrastructure\WordPress\ApprovedMixedTaxConfiguration())->apply((int)$argv[2]),JSON_PRETTY_PRINT),"\n";
