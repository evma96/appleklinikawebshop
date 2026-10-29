<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
spl_autoload_register(static function(string $class):void{$p='Appleklinika\\BackOffice\\';if(str_starts_with($class,$p)){require_once dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($p))).'.php';}});
function current_user_can($cap,...$args):bool{return $cap!=='publish_products';}
function nocache_headers():void{}
function check_ajax_referer(...$args):bool{return true;}
function wp_send_json_error(array $data,int $status):never{$expected=($GLOBALS['argv'][1]??'')==='unauthorized-publish'?403:422;if($status!==$expected)throw new RuntimeException('Unexpected guard response '.$status);echo "Intake guard: HTTP $status, ".$data['message']."; no product service or database reached.\n";exit(0);}
$_SERVER['REQUEST_METHOD']='POST';$_POST['save_mode']=($argv[1]??'')==='unauthorized-publish'?'publish':'unrecognized';
(new Appleklinika\BackOffice\Interfaces\DeviceIntakeController())->create();
throw new RuntimeException('Guard did not stop request.');
