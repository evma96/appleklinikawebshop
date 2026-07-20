<?php

declare(strict_types=1);

namespace Appleklinika\Inventory\Interfaces\Admin;

use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionCommand;
use Appleklinika\Inventory\Application\ProductCondition\SaveProductConditionHandler;
use Appleklinika\Inventory\Domain\ProductCondition\Grade;
use Appleklinika\Inventory\Infrastructure\WordPress\DeviceCatalogRepository;
use Appleklinika\Inventory\Infrastructure\WordPress\WooProductConditionRepository;
use Appleklinika\Inventory\Domain\ProductCondition\StorageCapacityCatalog;

final class ProductConditionFields
{
    private const NONCE_ACTION = 'appleklinika_save_product_condition';
    private const NONCE_NAME = 'appleklinika_product_condition_nonce';

    public function __construct(
        private readonly SaveProductConditionHandler $saveHandler,
        private readonly WooProductConditionRepository $repository,
        private readonly DeviceCatalogRepository $deviceCatalogRepository
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render']);
        add_action('woocommerce_process_product_meta', [$this, 'save']);
    }

    public function render(): void
    {
        global $post;

        if (! $post instanceof \WP_Post) {
            return;
        }

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<div class="options_group">';

        woocommerce_wp_select([
            'id' => 'appleklinika_device_type',
            'label' => 'Készüléktípus',
            'options' => [
                '' => 'Automatikus / nincs megadva',
                'iphone' => 'iPhone',
                'ipad' => 'iPad',
                'macbook' => 'MacBook',
                'apple_watch' => 'Apple Watch',
            ],
            'description' => 'A webshop szűrői és termékkártya chipjei ezt használják kategóriafüggő megjelenítéshez.',
            'value' => $this->repository->get($post->ID, 'device_type'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_device_model',
            'label' => 'Apple modell',
            'options' => $this->deviceCatalogRepository->modelOptions(),
            'wrapper_class' => 'appleklinika-device-field',
            'value' => $this->repository->get($post->ID, 'device_model'),
        ]);

        woocommerce_wp_text_input([
            'id' => 'appleklinika_battery_health',
            'label' => 'Akkumulátor állapot (%)',
            'type' => 'number',
            'custom_attributes' => [
                'min' => '0',
                'max' => '100',
                'step' => '1',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'value' => $this->repository->get($post->ID, 'battery_health'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_battery_option',
            'label' => 'Akkumulátor opció',
            'options' => [
                'standard' => 'Standard',
                'aftermarket_new' => 'Új utángyártott akkumulátor',
                'factory_new' => 'Új gyári akkumulátor',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'iphone',
            ],
            'value' => $this->repository->get($post->ID, 'battery_option') ?: 'standard',
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_storage_capacity',
            'label' => 'Tárhely',
            'options' => ['' => 'Válassz tárhelyet'] + StorageCapacityCatalog::options(),
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'iphone ipad macbook',
            ],
            'value' => $this->repository->get($post->ID, 'storage_capacity'),
        ]);
        echo '<p id="appleklinika_storage_capacity_warning" class="form-field" hidden><span class="description"></span></p>';

        woocommerce_wp_select([
            'id' => 'appleklinika_color',
            'label' => 'Szín',
            'options' => $this->deviceCatalogRepository->colorOptions(),
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'iphone ipad macbook apple_watch',
            ],
            'value' => $this->repository->get($post->ID, 'color'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_sim_config',
            'label' => 'SIM konfiguráció',
            'options' => [
                '' => 'Válassz SIM konfigurációt',
                'dual_esim' => 'Dual eSIM',
                'physical_esim' => 'Fizikai + eSIM',
                'dual_physical' => 'Dual fizikai',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'iphone',
            ],
            'value' => $this->repository->get($post->ID, 'sim_config'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_connectivity',
            'label' => 'Kapcsolat / hálózat',
            'options' => [
                '' => 'Nincs megadva',
                'wifi' => 'Wi-Fi',
                'wifi_cellular' => 'Wi-Fi + Cellular',
                'gps' => 'GPS',
                'gps_cellular' => 'GPS + Cellular',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'ipad apple_watch',
            ],
            'value' => $this->repository->get($post->ID, 'connectivity'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_screen_size',
            'label' => 'Kijelzőméret',
            'options' => [
                '' => 'Nincs megadva',
                '13_inch' => '13"',
                '14_inch' => '14"',
                '15_inch' => '15"',
                '16_inch' => '16"',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'macbook',
            ],
            'value' => $this->repository->get($post->ID, 'screen_size'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_processor_chip',
            'label' => 'Chip',
            'options' => [
                '' => 'Nincs megadva',
                'm1' => 'M1',
                'm1_pro' => 'M1 Pro',
                'm1_max' => 'M1 Max',
                'm2' => 'M2',
                'm2_pro' => 'M2 Pro',
                'm2_max' => 'M2 Max',
                'm3' => 'M3',
                'm3_pro' => 'M3 Pro',
                'm3_max' => 'M3 Max',
                'm4' => 'M4',
                'm4_pro' => 'M4 Pro',
                'm4_max' => 'M4 Max',
                'm5' => 'M5',
                'm5_pro' => 'M5 Pro',
                'm5_max' => 'M5 Max',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'macbook',
            ],
            'value' => $this->repository->get($post->ID, 'processor_chip'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_ram_size',
            'label' => 'RAM',
            'options' => [
                '' => 'Nincs megadva',
                '8_gb' => '8 GB',
                '16_gb' => '16 GB',
                '18_gb' => '18 GB',
                '24_gb' => '24 GB',
                '32_gb' => '32 GB',
                '36_gb' => '36 GB',
                '48_gb' => '48 GB',
                '64_gb' => '64 GB',
                '96_gb' => '96 GB',
                '128_gb' => '128 GB',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'macbook',
            ],
            'value' => $this->repository->get($post->ID, 'ram_size'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_case_size',
            'label' => 'Apple Watch tokméret',
            'options' => [
                '' => 'Nincs megadva',
                '40_mm' => '40 mm',
                '41_mm' => '41 mm',
                '42_mm' => '42 mm',
                '44_mm' => '44 mm',
                '45_mm' => '45 mm',
                '46_mm' => '46 mm',
                '49_mm' => '49 mm',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'apple_watch',
            ],
            'value' => $this->repository->get($post->ID, 'case_size'),
        ]);

        woocommerce_wp_select([
            'id' => 'appleklinika_case_material',
            'label' => 'Apple Watch tok anyaga / színe',
            'options' => [
                '' => 'Nincs megadva',
                'aluminium' => 'Alumínium',
                'stainless_steel' => 'Rozsdamentes acél',
                'titanium' => 'Titán',
            ],
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'apple_watch',
            ],
            'value' => $this->repository->get($post->ID, 'case_material'),
        ]);

        woocommerce_wp_text_input([
            'id' => 'appleklinika_strap',
            'label' => 'Szíj',
            'wrapper_class' => 'appleklinika-device-field',
            'custom_attributes' => [
                'data-ak-device-types' => 'apple_watch',
            ],
            'value' => $this->repository->get($post->ID, 'strap'),
        ]);
        $this->renderDeviceTypeScript($post->ID);

        woocommerce_wp_select([
            'id' => 'appleklinika_warranty_duration',
            'label' => 'Garancia',
            'options' => [
                '' => 'Válassz garanciát',
                '3_months' => '3 hónap',
                '6_months' => '6 hónap',
                '12_months' => '12 hónap',
                '24_months' => '24 hónap',
                '36_months' => '36 hónap',
            ],
            'value' => $this->repository->get($post->ID, 'warranty_duration'),
        ]);

        woocommerce_wp_textarea_input([
            'id' => 'appleklinika_accessories',
            'label' => 'Tartozékok',
            'value' => $this->repository->get($post->ID, 'accessories'),
        ]);

        woocommerce_wp_textarea_input([
            'id' => 'appleklinika_short_device_description',
            'label' => 'Rövid leírás',
            'value' => $this->repository->get($post->ID, 'short_device_description'),
        ]);

        woocommerce_wp_text_input([
            'id' => 'appleklinika_internal_identifier',
            'label' => 'Belső azonosító / IMEI',
            'description' => 'Csak admin használatra. Frontenden nem jelenhet meg.',
            'value' => $this->repository->get($post->ID, 'internal_identifier'),
        ]);

        $this->gradeSelect('body_grade', 'Ház állapota', $post->ID);
        $this->gradeSelect('camera_island_grade', 'Kamerasziget állapota', $post->ID);
        $this->gradeSelect('display_grade', 'Kijelző állapota', $post->ID);

        $this->gradeSelect('overall_grade', 'Összesített grade', $post->ID);

        echo '</div>';
    }

    public function save(int $productId): void
    {
        if (! current_user_can('edit_product', $productId)) {
            return;
        }

        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        $this->saveHandler->handle(new SaveProductConditionCommand($productId, [
            'device_type' => $this->postedValue('appleklinika_device_type'),
            'device_model' => $this->postedValue('appleklinika_device_model'),
            'battery_health' => $this->postedValue('appleklinika_battery_health'),
            'battery_option' => $this->postedValue('appleklinika_battery_option', 'standard'),
            'storage_capacity' => $this->postedValue('appleklinika_storage_capacity'),
            'color' => $this->postedValue('appleklinika_color'),
            'sim_config' => $this->postedValue('appleklinika_sim_config'),
            'connectivity' => $this->postedValue('appleklinika_connectivity'),
            'screen_size' => $this->postedValue('appleklinika_screen_size'),
            'processor_chip' => $this->postedValue('appleklinika_processor_chip'),
            'ram_size' => $this->postedValue('appleklinika_ram_size'),
            'case_size' => $this->postedValue('appleklinika_case_size'),
            'case_material' => $this->postedValue('appleklinika_case_material'),
            'strap' => $this->postedValue('appleklinika_strap'),
            'warranty_duration' => $this->postedValue('appleklinika_warranty_duration'),
            'accessories' => $this->postedValue('appleklinika_accessories'),
            'short_device_description' => $this->postedValue('appleklinika_short_device_description'),
            'internal_identifier' => $this->postedValue('appleklinika_internal_identifier'),
            'body_grade' => $this->postedValue('appleklinika_body_grade', Grade::B),
            'camera_island_grade' => $this->postedValue('appleklinika_camera_island_grade', Grade::B),
            'display_grade' => $this->postedValue('appleklinika_display_grade', Grade::B),
            'overall_grade' => $this->postedValue('appleklinika_overall_grade', Grade::B),
        ]));
    }

    private function gradeSelect(string $field, string $label, int $productId): void
    {
        woocommerce_wp_select([
            'id' => 'appleklinika_' . $field,
            'label' => $label,
            'options' => Grade::options(),
            'value' => $this->repository->get($productId, $field) ?: Grade::B,
        ]);
    }

    private function postedValue(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : $default;
    }

    private function renderDeviceTypeScript(int $productId): void
    {
        $catalog = [];

        foreach ($this->deviceCatalogRepository->all() as $device) {
            $catalog[$device['key']] = [
                'name' => $device['name'],
                'type' => $device['type'],
                'colors' => $device['colors'],
                'storage_capacity_keys' => $device['storage_capacity_keys'],
            ];
        }

        $catalogJson = wp_json_encode($catalog);
        $watchOptionsJson = wp_json_encode($this->deviceCatalogRepository->watchOptionsByModel());
        $selectedJson = wp_json_encode([
            'model' => $this->repository->get($productId, 'device_model'),
            'storage' => $this->repository->get($productId, 'storage_capacity'),
            'color' => $this->repository->get($productId, 'color'),
            'connectivity' => $this->repository->get($productId, 'connectivity'),
            'caseSize' => $this->repository->get($productId, 'case_size'),
            'caseMaterial' => $this->repository->get($productId, 'case_material'),
        ]);

        echo <<<HTML
<script>
(function(){
const catalog={$catalogJson};
const watchOptions={$watchOptionsJson};
const selected={$selectedJson};
const typeSelect=document.getElementById("appleklinika_device_type");
const modelSelect=document.getElementById("appleklinika_device_model");
const storageSelect=document.getElementById("appleklinika_storage_capacity");
const storageWarning=document.getElementById("appleklinika_storage_capacity_warning");
const colorSelect=document.getElementById("appleklinika_color");
const connectivitySelect=document.getElementById("appleklinika_connectivity");
const caseSizeSelect=document.getElementById("appleklinika_case_size");
const caseMaterialSelect=document.getElementById("appleklinika_case_material");
if(!typeSelect||!modelSelect||!colorSelect){return;}
function normalizeType(type){return {iphone:"iphone",ipad:"ipad",mac:"macbook",macbook:"macbook",watch:"apple_watch",apple_watch:"apple_watch"}[type]||type||"";}
function selectedType(){return normalizeType(typeSelect.value);}
function option(value,label){const item=document.createElement("option");item.value=value;item.textContent=label;return item;}
function deviceMatchesType(device,type){return !type||normalizeType(device.type)===type;}
function optionLabels(select){const labels={};if(!select){return labels;}Array.from(select.options).forEach(function(item){labels[item.value]=item.textContent;});return labels;}
const labels={
  connectivity: optionLabels(connectivitySelect),
  storage: optionLabels(storageSelect),
  caseSize: optionLabels(caseSizeSelect),
  caseMaterial: optionLabels(caseMaterialSelect)
};
function unique(values){return Array.from(new Set((values||[]).filter(Boolean)));}
function currentWatchRule(){return watchOptions[modelSelect.value]||null;}
function watchColorsForRule(rule, material){
  if(!rule||!rule.colors_by_material){return null;}
  if(material&&rule.colors_by_material[material]){return unique(rule.colors_by_material[material]);}
  return unique(Object.keys(rule.colors_by_material).reduce(function(all,key){return all.concat(rule.colors_by_material[key]||[]);},[]));
}
function setFilteredOptions(select, labelMap, allowed, currentValue, placeholderLabel, autoSelectSingle){
  if(!select){return;}
  const current=select.value||currentValue||"";
  const values=unique(allowed);
  select.innerHTML="";
  select.appendChild(option("",placeholderLabel));
  values.forEach(function(value){if(labelMap[value]===undefined){return;}select.appendChild(option(value,labelMap[value]));});
  if(values.indexOf(current)!==-1){select.value=current;}
  else if(autoSelectSingle&&values.length===1){select.value=values[0];}
  else{select.value="";}
}
function refreshDeviceFields(){
  const type=selectedType();
  document.querySelectorAll(".appleklinika-device-field").forEach(function(row){
    const control=row.querySelector("[data-ak-device-types]");
    const allowed=(control&&control.dataset.akDeviceTypes?control.dataset.akDeviceTypes.split(/\\s+/):[]);
    const visible=!type||allowed.length===0||allowed.indexOf(type)!==-1;
    row.style.display=visible?"":"none";
  });
}
function refreshModels(){
  const type=selectedType();
  const current=modelSelect.value||selected.model;
  modelSelect.innerHTML="";
  modelSelect.appendChild(option("","Válassz modellt"));
  Object.keys(catalog).forEach(function(key){const device=catalog[key];if(!deviceMatchesType(device,type)){return;}const item=option(key,device.name);if(key===current){item.selected=true;}modelSelect.appendChild(item);});
  if(current&&catalog[current]&&(!type||deviceMatchesType(catalog[current],type))&&modelSelect.value!==current){const item=option(current,catalog[current].name||current);item.selected=true;modelSelect.appendChild(item);}
}
function refreshConnectivity(){
  const type=selectedType();
  if(type==="ipad"){setFilteredOptions(connectivitySelect,labels.connectivity,["wifi","wifi_cellular"],selected.connectivity,"Nincs megadva",false);return;}
  if(type==="apple_watch"){
    const rule=currentWatchRule();
    setFilteredOptions(connectivitySelect,labels.connectivity,(rule&&rule.connectivity)||["gps","gps_cellular"],selected.connectivity,"Nincs megadva",true);
    return;
  }
  setFilteredOptions(connectivitySelect,labels.connectivity,[],"","Nincs megadva",false);
}
function refreshStorageCapacity(){
  if(!storageSelect){return;}
  const current=storageSelect.value;
  if(selectedType()!=="iphone"){
    if(storageWarning){storageWarning.hidden=true;}
    return;
  }
  const model=catalog[modelSelect.value]||null;
  const allowed=(model&&model.storage_capacity_keys)||[];
  const valid=allowed.indexOf(current)!==-1;
  setFilteredOptions(storageSelect,labels.storage,allowed,current,"Válassz tárhelyet",false);
  if(current&& !valid){
    const label=labels.storage[current]||current;
    storageSelect.appendChild(option(current,label+" — jelenleg nem érvényes ehhez a modellhez"));
    storageSelect.value=current;
    if(storageWarning){storageWarning.hidden=false;storageWarning.querySelector(".description").textContent="A tárolt tárhelyérték nem tartozik a kiválasztott iPhone modellhez. Javítsd ki mentés előtt; a rendszer nem cseréli le automatikusan.";}
    return;
  }
  if(storageWarning){storageWarning.hidden=true;}
}
function refreshWatchFields(){
  if(selectedType()!=="apple_watch"){
    setFilteredOptions(caseSizeSelect,labels.caseSize,[],"","Nincs megadva",false);
    setFilteredOptions(caseMaterialSelect,labels.caseMaterial,[],"","Nincs megadva",false);
    return;
  }
  const rule=currentWatchRule();
  setFilteredOptions(caseSizeSelect,labels.caseSize,(rule&&rule.case_sizes)||["40_mm","41_mm","42_mm","44_mm","45_mm","46_mm","49_mm"],selected.caseSize,"Nincs megadva",true);
  setFilteredOptions(caseMaterialSelect,labels.caseMaterial,(rule&&rule.case_materials)||["aluminium","stainless_steel","titanium"],selected.caseMaterial,"Nincs megadva",true);
}
function refreshColors(){
  const current=colorSelect.value||selected.color;
  const model=modelSelect.value;
  let colors=(catalog[model]&&catalog[model].colors)||{};
  if(selectedType()==="apple_watch"){
    const allowedWatchColors=watchColorsForRule(currentWatchRule(),caseMaterialSelect?caseMaterialSelect.value:"");
    if(allowedWatchColors){
      colors=allowedWatchColors.reduce(function(filtered,key){
        if(catalog[model]&&catalog[model].colors&&catalog[model].colors[key]!==undefined){filtered[key]=catalog[model].colors[key];}
        return filtered;
      },{});
    }
  }
  colorSelect.innerHTML="";
  colorSelect.appendChild(option("","Válassz színt"));
  Object.keys(colors).forEach(function(key){const item=option(key,colors[key]);if(key===current){item.selected=true;}colorSelect.appendChild(item);});
  if(current&&colors[current]===undefined){colorSelect.value="";}
}
function refreshAdminProductFields(){
  refreshDeviceFields();
  refreshModels();
  refreshConnectivity();
  refreshStorageCapacity();
  refreshWatchFields();
  refreshColors();
}
typeSelect.addEventListener("change",function(){refreshAdminProductFields();});
modelSelect.addEventListener("change",function(){refreshConnectivity();refreshStorageCapacity();refreshWatchFields();refreshColors();});
if(caseMaterialSelect){caseMaterialSelect.addEventListener("change",refreshColors);}
refreshAdminProductFields();
})();
</script>
HTML;
    }
}
