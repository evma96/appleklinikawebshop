<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Interfaces;
use Appleklinika\BackOffice\Infrastructure\WooDeviceProducts;
use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Application\DeviceProduct\DeviceIntakeSchema;
use Appleklinika\BackOffice\Infrastructure\CanonicalRuntime;

final class DeviceProductsPage
{
    public function listing(array $result):void
    {
        $sku=is_string($_GET['sku']??null)?wp_unslash($_GET['sku']):'';
        $state=is_string($_GET['state']??null)?wp_unslash($_GET['state']):'';
        $family=is_string($_GET['family']??null)?wp_unslash($_GET['family']):'';
        $stock=is_string($_GET['stock']??null)?wp_unslash($_GET['stock']):'';
        echo '<p class="akbo-help">A piszkozatok is láthatók. A webshopban csak a közzétett készülékek jelennek meg.</p>';
        echo '<form class="akbo-card akbo-device-filters" method="get"><input type="hidden" name="view" value="devices"><label>Keresés (név, modell, cikkszám, IMEI, sorozatszám)<input name="sku" value="'.esc_attr($sku).'" maxlength="100" type="search"></label><label>Közzététel<select name="state"><option value="">Minden állapot</option>';
        foreach(WooDeviceProducts::STATUSES as $key=>$label)echo '<option value="'.esc_attr($key).'"'.selected($state,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label><label>Család<select name="family"><option value="">Minden család</option>';
        foreach(DeviceIntakeSchema::FAMILIES as $key=>$label)echo '<option value="'.esc_attr($key).'"'.selected($family,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label><label>Készlet<select name="stock"><option value="">Minden készlet</option>';
        foreach(['instock'=>'Készleten','outofstock'=>'Elfogyott'] as $key=>$label)echo '<option value="'.esc_attr($key).'"'.selected($stock,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label><button class="akbo-button" type="submit">Keresés</button><a href="'.esc_url(self::url()).'">Szűrés törlése</a></form>';
        echo '<p>'.esc_html((string)$result['total']).' készülék</p><div class="akbo-device-grid">';
        foreach($result['items'] as $d){
            echo '<article class="akbo-card akbo-device-card" data-product-id="'.esc_attr((string)$d['id']).'"><header><img src="'.esc_url($d['thumbnail']).'" alt="'.esc_attr($d['name']).'" loading="lazy"><div><h2>'.esc_html($d['name']).'</h2><p>'.esc_html($d['sku']).'</p><span class="akbo-badge">'.esc_html($d['status_label']).'</span></div></header><dl>';
            $this->fact('Család / modell',esc_html($d['family'].' · '.$d['model']));$this->fact('Állapot',esc_html($d['condition']));
            $this->prices($d);$this->fact('Készlet',esc_html($d['manage_stock']?($d['stock']??'—').' db':(($d['stock_status']==='instock'?'Készleten':'Elfogyott').' · nem kezelt')));$this->fact('Woo termékazonosító','#'.esc_html((string)$d['id']));
            echo '</dl><div class="akbo-device-actions">';$this->actions($d);echo '</div></article>';
        }
        echo '</div>';
        if(!$result['items'])echo '<p class="akbo-card">Nincs a szűrésnek megfelelő készülék.</p>';
        if($result['pages']>1){
            echo '<nav class="akbo-device-actions" aria-label="Készüléklista lapozás">';
            foreach([-1=>'Előző oldal',1=>'Következő oldal'] as $step=>$label){$page=$result['page']+$step;if($page>=1&&$page<=$result['pages'])echo '<a class="akbo-button akbo-button--secondary" href="'.esc_url(add_query_arg(['device_page'=>$page,'sku'=>$sku,'state'=>$state,'family'=>$family,'stock'=>$stock],self::url())).'">'.esc_html($label).'</a>';}
            echo '<span>'.esc_html($result['page'].' / '.$result['pages']).'</span></nav>';
        }
    }
    public function edit(array $result):void
    {
        $d=$result['device'];$v=$result['values'];
        echo '<p><a href="'.esc_url(self::url()).'">← Készülékek</a></p>';
        if($result['error']!=='')echo '<p class="akbo-notice akbo-notice--error" role="alert">'.esc_html($result['error']).'</p>';
        elseif(($_GET['updated']??'')==='1')echo '<p class="akbo-notice" role="status">A készülék módosításai elmentve.</p>';
        echo '<section class="akbo-card"><h2>'.esc_html($d['name']).'</h2><p>'.esc_html($d['sku'].' · #'.$d['id'].' · '.$d['family'].' · '.$d['model']).'</p><p>'.esc_html($d['status_label'].' · készlet: '.($d['stock']??'—').' db').'</p><div class="akbo-device-actions">';
        $this->actions($d,false);if($d['native_edit_url'])echo '<a href="'.esc_url(CanonicalRuntime::storefront($d['native_edit_url'])).'">Teljes WooCommerce szerkesztő</a>';echo '</div></section>';
        if(!$d['offer_editable'])echo '<p class="akbo-card">Korábban rögzített készülék. Az adatait a teljes WooCommerce szerkesztőben ellenőrizheted; a Back Office nem alakítja át automatikusan.</p>';
        if($d['offer_editable']){
        echo '<form method="post" action="'.esc_url($d['edit_url']).'" class="akbo-card akbo-device-edit" id="akbo-device-edit">';
        wp_nonce_field('akbo_device_edit_'.$d['id']);
        echo '<input type="hidden" name="expected" value="'.esc_attr($d['version']).'"><h2>Ár és állapot</h2><div class="akbo-intake-grid">';
        echo '<label>Normál ár ('.esc_html(get_woocommerce_currency()).')<input name="regular_price" type="number" min="0.01" max="9999999999" step="0.01" required value="'.esc_attr($v['regular_price']).'"></label>';
        echo '<label>Akciós ár – opcionális<input name="sale_price" type="number" min="0" max="9999999999" step="0.01" value="'.esc_attr($v['sale_price']).'"></label><label>Állapot<select name="overall_grade" required>';
        foreach(Grade::options() as $key=>$label)echo '<option value="'.esc_attr($key).'"'.selected($v['overall_grade']??$v['grade'],$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label></div><p class="akbo-help">Üres akciós ár esetén nincs akció. Az akciós árnak kisebbnek kell lennie a normál árnál. A készlet, az azonosítók, a fotók és a közzététel itt nem változnak.</p><button type="submit" class="akbo-button">Módosítások mentése</button></form>';
        }
        if($d['publishable']){
            echo '<form method="post" action="'.esc_url($d['edit_url']).'" class="akbo-card akbo-device-edit"><h2>Közzététel</h2><p>A készülék a webshop szokásos megjelenítési szabályai szerint láthatóvá válhat.</p>';
            wp_nonce_field('akbo_device_publish_'.$d['id'],'_akbo_publish_nonce');
            echo '<input type="hidden" name="product_action" value="publish"><input type="hidden" name="expected" value="'.esc_attr($d['version']).'"><button type="submit" class="akbo-button">Közzététel</button></form>';
        }
        echo '<section class="akbo-card"><h2>Mentett termékfotók</h2><div class="akbo-intake-saved-photos">';foreach($d['images'] as $i=>$photo)echo '<figure><img src="'.esc_url($photo['url']).'" alt="'.esc_attr($i===0?'Főfotó':($i+1).'. fotó').'"><figcaption>'.esc_html($i===0?'Főfotó':($i+1).'. fotó').'</figcaption></figure>';echo '</div><details><summary>Privát készülékazonosítók</summary><p>IMEI: '.esc_html($d['imei']?:'—').'</p><p>Sorozatszám: '.esc_html($d['serial_number']?:'—').'</p></details></section>';
    }
    private function prices(array $d):void
    {
        $this->fact('Normál ár',self::money($d['regular_price']));if($d['sale_price']!=='')$this->fact('Akciós ár',self::money($d['sale_price']));$this->fact('Eladási ár',self::money($d['price']));
    }
    private static function money(string $price):string {return $price===''?'—':wp_kses_post(wc_price($price));}
    private function fact(string $name,string $html):void {echo '<div><dt>'.esc_html($name).'</dt><dd>'.$html.'</dd></div>';}
    private function actions(array $d,bool $edit=true):void
    {
        if($edit&&$d['edit_url'])echo '<a class="akbo-button" href="'.esc_url($d['edit_url']).'">Szerkesztés</a>';
        if($d['preview_url'])echo '<a class="akbo-button akbo-button--secondary" href="'.esc_url($d['preview_url']).'" target="_blank" rel="noopener">Előnézet</a>';
    }
    private static function url():string {return add_query_arg('view','devices',home_url('/backoffice/'));}
}
