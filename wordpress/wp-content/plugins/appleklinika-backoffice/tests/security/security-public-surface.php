<?php
declare(strict_types=1);
$capabilities=[];$hooks=[];$author=false;$removed=[];$status=0;$nocache=false;
function current_user_can($cap){global $capabilities;return in_array($cap,$capabilities,true);}
function add_filter(...$args){global $hooks;$hooks[]=$args;}
function add_action(...$args){global $hooks;$hooks[]=$args;}
function is_author(){global $author;return $author;}
function status_header($n){global $status;$status=$n;}
function nocache_headers(){global $nocache;$nocache=true;}
function remove_action(...$args){global $removed;$removed[]=$args;}
class WP_Error {public function __construct(public $code,public $message,public $data){}}
class Request {public function __construct(private $route,private $method='GET'){}public function get_route(){return $this->route;}public function get_method(){return $this->method;}}
require __DIR__.'/../../src/Infrastructure/PublicSurfaceSecurity.php';
$s=new Appleklinika\BackOffice\Infrastructure\PublicSurfaceSecurity();$n=0;
function check($v){global $n;if(!$v)throw new RuntimeException('Security regression '.($n+1));++$n;}
$s->register();check(count($hooks)===4);
foreach(['/wp/v2/users','/wp/v2/users/1','/wp/v2/users/27/']as$route){$x=$s->users(null,null,new Request($route));check($x instanceof WP_Error && $x->data['status']===403);}
foreach(['/wp/v2/users/me','/wc/store/v1/cart','/wc/store/v1/checkout','/wp/v2/posts']as$route)check($s->users('unchanged',null,new Request($route))==='unchanged');
check($s->users('unchanged',null,new Request('/wp/v2/users','POST'))==='unchanged');
foreach(['list_users','edit_posts']as$cap){$capabilities=[$cap];check($s->users('allowed',null,new Request('/wp/v2/users'))==='allowed');}
$capabilities=[];$wp_query=new class {public bool $blocked=false;public function set_404(){$this->blocked=true;}};$_GET=['author'=>'1'];$s->author();check($wp_query->blocked&&$status===404&&$nocache&&count($removed)===1);
echo "Public surface security: $n assertions passed; no database or network.\n";
