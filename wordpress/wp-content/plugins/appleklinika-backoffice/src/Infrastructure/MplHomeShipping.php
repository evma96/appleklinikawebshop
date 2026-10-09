<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;
use Appleklinika\BackOffice\Domain\MplParcelRules;

final class MplHomeShipping extends \WC_Shipping_Method
{
    public function __construct($instanceId = 0)
    {
        $this->id = 'ak_mpl_home'; $this->instance_id = absint($instanceId);
        $this->method_title = 'MPL házhozszállítás';
        $this->method_description = 'Bruttó vásárlói szállítási díjak. Kézi feladásnál fix díj, a csomag ellenőrzése az üzlet feladata. Automatikus módban becsomagolt súly és méret szükséges.';
        $this->supports = ['shipping-zones','instance-settings','instance-settings-modal'];
        $this->instance_form_fields = ['title'=>['title'=>'Megnevezés','type'=>'text','default'=>'MPL házhozszállítás']];
        $this->instance_form_fields['manual_price'] = ['title'=>'Kézi feladás – fix bruttó Ft','type'=>'price','default'=>'2090'];
        foreach (['home_10'=>['0–10 kg',2090],'home_20'=>['10 kg felett – 20 kg',3140],'home_40'=>['20 kg felett – 40 kg',6300]] as $key=>$row) {
            $this->instance_form_fields[$key] = ['title'=>$row[0].' – bruttó Ft','type'=>'price','default'=>(string)$row[1]];
        }
        $this->init_settings(); $this->title = $this->get_option('title','MPL házhozszállítás'); $this->tax_status = 'taxable';
        add_action('woocommerce_update_options_shipping_'.$this->id, [$this,'process_admin_options']);
    }
    public function calculate_shipping($package = [])
    {
        if (($package['destination']['country'] ?? '') !== 'HU') { return; }
        $parcel = MplPackage::fromContents($package['contents'] ?? []);
        if (!MplPackage::eligible('home', $parcel)) { return; }
        $rates=[]; foreach (['home_10','home_20','home_40'] as $key) { $rates[$key]=$this->get_option($key); }
        $manual = $this->get_option('manual_price');
        $gross = MplPackage::manualDispatch() ? (is_numeric($manual) && (float)$manual >= 0 ? (float)$manual : null) : MplParcelRules::price($parcel['kg'], $rates);
        if ($gross === null) { return; }
        $net = $gross;
        if (wc_tax_enabled()) { $net -= array_sum(\WC_Tax::calc_inclusive_tax($gross, \WC_Tax::get_shipping_tax_rates())); }
        $this->add_rate(['id'=>$this->get_rate_id(),'label'=>$this->title,'cost'=>$net,'package'=>$package]);
    }
}
