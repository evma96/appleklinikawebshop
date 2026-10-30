<?php

declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

/** Explicit operator setup using native Woo tax classes/rates. Never runs on checkout. */
final class ApprovedMixedTaxConfiguration
{
    public const IPHONE = 'ak-used-iphone-margin';
    public const ACCESSORY = 'ak-accessories-27';

    public function apply(int $iphoneId): array
    {
        $phone = wc_get_product($iphoneId);
        if (!$phone || $phone->get_meta('_appleklinika_device_type') !== 'iphone'
            || $phone->get_meta('_ak_mixed_invoice_qa') !== '20261030') {
            throw new \RuntimeException('An explicitly marked disposable used iPhone fixture is required. Other families are not classified automatically.');
        }
        if (explode(':', (string)get_option('woocommerce_default_country'))[0] !== 'HU'
            || !function_exists('WC_Szamlazz') || WC_Szamlazz()->get_option('afakulcs') !== '') {
            throw new \RuntimeException('Hungarian base location and no global invoice VAT override required.');
        }
        $accessories = [];
        foreach (get_posts(['post_type'=>'product','post_status'=>['publish','draft','private'],'posts_per_page'=>-1,
            'fields'=>'ids','meta_key'=>'_appleklinika_protection_group']) as $id) {
            $p = wc_get_product($id);
            if ($p && in_array($p->get_meta('_appleklinika_protection_group'), ['glass','case','camera','adapter'], true)) $accessories[] = $p;
        }
        if (count($accessories) < 2) throw new \RuntimeException('Accessory catalog missing.');
        $this->rate(self::IPHONE, 'AK used iPhone margin', '0', 'K.AFA');
        $this->rate(self::ACCESSORY, 'AK accessories 27', '27', '27%');
        foreach (['woocommerce_calc_taxes'=>'yes','woocommerce_prices_include_tax'=>'yes',
            'woocommerce_tax_display_shop'=>'incl','woocommerce_tax_display_cart'=>'incl'] as $key=>$value) update_option($key,$value);
        $phone->set_tax_status('taxable'); $phone->set_tax_class(self::IPHONE); $phone->save();
        $ids=[];
        foreach ($accessories as $p) {
            $price = $p->get_price();
            $p->set_tax_status('taxable'); $p->set_tax_class(self::ACCESSORY); $p->save();
            if ($p->get_price() !== $price) throw new \RuntimeException('Retail price changed unexpectedly.');
            $ids[] = $p->get_id();
        }
        return ['iphone'=>$iphoneId,'accessories'=>$ids,'gross_prices_unchanged'=>true,'other_device_families_changed'=>false];
    }

    private function rate(string $slug, string $name, string $percent, string $label): void
    {
        $existing = \WC_Tax::get_rates_for_tax_class($slug);
        if ($existing) {
            if (count($existing)!==1) throw new \RuntimeException('Unexpected tax rates for '.$slug);
            $rate = reset($existing);
            if ($rate->tax_rate_country!=='HU' || $rate->tax_rate_state!=='' || (float)$rate->tax_rate!==(float)$percent
                || $rate->tax_rate_name!==$label || (int)$rate->tax_rate_compound!==0 || (int)$rate->tax_rate_shipping!==0) {
                throw new \RuntimeException('Existing tax rate conflicts with approved configuration: '.$slug);
            }
            return;
        }
        if (!in_array($slug, \WC_Tax::get_tax_class_slugs(), true)) {
            $result=\WC_Tax::create_tax_class($name,$slug);
            if(is_wp_error($result)) throw new \RuntimeException($result->get_error_message());
        }
        $id=\WC_Tax::_insert_tax_rate(['tax_rate_country'=>'HU','tax_rate_state'=>'','tax_rate'=>$percent,
            'tax_rate_name'=>$label,'tax_rate_priority'=>1,'tax_rate_compound'=>0,'tax_rate_shipping'=>0,
            'tax_rate_order'=>0,'tax_rate_class'=>$slug]);
        if(!$id) throw new \RuntimeException('Tax rate creation failed.');
    }
}
