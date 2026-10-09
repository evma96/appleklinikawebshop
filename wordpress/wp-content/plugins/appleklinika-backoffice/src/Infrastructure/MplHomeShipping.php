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
        $this->method_description = 'MPL általános webáruházi díjak, bruttó Ft. Becsomagolt terméksúly és méret szükséges. Ellenőrzött díjak: 2026-10-09.';
        $this->supports = ['shipping-zones','instance-settings','instance-settings-modal'];
        $this->instance_form_fields = ['title'=>['title'=>'Megnevezés','type'=>'text','default'=>'MPL házhozszállítás']];
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
        if (!MplParcelRules::eligible('home', $parcel['kg'], $parcel['dimensions'], $parcel['value'])) { return; }
        $rates=[]; foreach (['home_10','home_20','home_40'] as $key) { $rates[$key]=$this->get_option($key); }
        $gross = MplParcelRules::price($parcel['kg'], $rates); if ($gross === null) { return; }
        $net = $gross;
        if (wc_tax_enabled()) { $net -= array_sum(\WC_Tax::calc_inclusive_tax($gross, \WC_Tax::get_shipping_tax_rates())); }
        $this->add_rate(['id'=>$this->get_rate_id(),'label'=>$this->title,'cost'=>$net,'package'=>$package]);
    }
}
