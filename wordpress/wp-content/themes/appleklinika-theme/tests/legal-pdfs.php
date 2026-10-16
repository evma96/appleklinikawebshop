<?php
declare(strict_types=1);
final class WP_Post { public string $post_status='publish'; public string $post_content=''; }
function add_shortcode(...$x){} function add_action(...$x){} function add_filter(...$x){}
function get_template_directory_uri(){return 'https://fixture.invalid/theme';}
function esc_attr($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');} function esc_html($v){return esc_attr($v);} function esc_url($v){return esc_attr($v);}
function get_post($id){return $GLOBALS['pages'][$id]??null;}
function get_option($key,$default=0){return ['woocommerce_terms_page_id'=>10,'wp_page_for_privacy_policy'=>11][$key]??$default;}
function home_url($path){return 'https://fixture.invalid'.$path;}
function wp_parse_url($url,$part){return parse_url($url,$part);}
require dirname(__DIR__).'/inc/legal-pdfs.php';
$n=0;function check($ok,$why){global$n;++$n;if(!$ok)throw new RuntimeException($why);}
foreach(appleklinika_legal_pdf_catalog()as$key=>$document){
 check(hash_file('sha256',dirname(__DIR__).'/assets/legal/'.$document['file'])===$document['sha256'],'PDF must remain byte-identical.');
 $html=appleklinika_render_legal_pdf(['document'=>$key]);
 check(str_contains($html,'download="'.$document['file'].'"'),'Download must reference exact binary.');
 check(str_contains($html,$document['updated'])&&str_contains($html,'2026.09.20'),'Source version dates must match.');
 check(substr_count($html,$document['file'])===4,'Open, download and embedded viewer must share one PDF.');
}
check(appleklinika_render_legal_pdf(['document'=>'invalid'])==='','No invented/legal fallback document.');
foreach([10=>'terms',11=>'privacy']as$id=>$key){$p=new WP_Post();$p->post_content='[appleklinika_legal_pdf document="'.$key.'"]';$GLOBALS['pages'][$id]=$p;}
check(appleklinika_legal_terms_permalink('?page_id=10',10)==='https://fixture.invalid/aszf/','Terms canonical route.');
check(appleklinika_legal_terms_permalink('?page_id=11',11)==='?page_id=11','Privacy canonical route unchanged.');
foreach(['/aszf/'=>10,'/adatvedelem'=>11]as$path=>$id){$_SERVER['REQUEST_URI']=$path;$wp=(object)['query_vars'=>['error'=>'404']];appleklinika_legal_pdf_request($wp);check($wp->query_vars===['page_id'=>$id],'Plain permalink alias resolves native page.');}
$_SERVER['REQUEST_URI']='/other';$wp=(object)['query_vars'=>['p'=>123]];appleklinika_legal_pdf_request($wp);check($wp->query_vars===['p'=>123],'Other routes unchanged.');
$GLOBALS['pages'][10]->post_content='old sample';check(appleklinika_legal_terms_permalink('?page_id=10',10)==='?page_id=10','No activation before content install.');
echo "Final legal PDF presentation: $n assertions passed; no DB/network.\n";
