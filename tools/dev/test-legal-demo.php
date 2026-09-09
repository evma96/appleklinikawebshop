<?php

declare(strict_types=1);

require_once __DIR__ . '/provision-legal-demo.php';
$count = 0;
$check = static function (bool $condition, string $message) use (&$count): void {
    ++$count;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$check(ak_legal_demo_target_allowed('http://localhost:8080', 'local', false), 'LOCAL allowed.');
$check(! ak_legal_demo_target_allowed('http://localhost:8080', 'production', false), 'Mislabeled LOCAL requires explicit flag.');
$check(ak_legal_demo_target_allowed('http://localhost:8080', 'production', true), 'Explicit LOCAL-only exception.');
$check(ak_legal_demo_target_allowed('https://teszt.appleklinika.com', 'staging', false), 'Exact staging allowed.');
$check(! ak_legal_demo_target_allowed('https://teszt.appleklinika.com', 'production', true), 'Production environment never allowed on TEST hostname.');
$check(! ak_legal_demo_target_allowed('https://appleklinika.com', 'staging', true), 'Production hostname refused even with flag.');
$check(! ak_legal_demo_target_allowed('https://teszt.appleklinika.com.evil.test', 'staging', true), 'Hostname suffix cannot bypass guard.');
$check(! ak_legal_demo_target_allowed('https://unrelated.test', 'staging', true), 'Unrelated host refused.');
$documents = ak_legal_demo_documents();
$check(count($documents) === 8, 'Exactly eight demo documents.');
$check(count(array_unique(array_column($documents, 'slug'))) === 8, 'Unique stable demo slugs.');
foreach ($documents as $document) {
    $content = ak_legal_demo_content($document['topics'], ['Privacy' => 'https://teszt.appleklinika.com/?page_id=1']);
    $check(str_contains($content, 'TESZT / MINTASZÖVEG – NEM VÉGLEGES JOGI DOKUMENTUM'), 'Visible warning.');
    $check(str_contains($content, 'Nem jogi tanács') && substr_count($content, '<h2') >= 6 && substr_count($content, '<ul>') >= 5, 'Structured non-authoritative long content.');
    $check($content === ak_legal_demo_content($document['topics'], ['Privacy' => 'https://teszt.appleklinika.com/?page_id=1']), 'Deterministic content.');
}
$blocks = [['blockName' => 'woocommerce/checkout', 'attrs' => ['unchanged' => true], 'innerBlocks' => [['blockName' => 'woocommerce/checkout-terms-block', 'attrs' => ['className' => 'preserve'], 'innerBlocks' => []]]]];
$check(ak_legal_demo_terms_checkbox($blocks) === 1, 'One native Terms block.');
$check($blocks[0]['innerBlocks'][0]['attrs'] === ['className' => 'preserve', 'checkbox' => true], 'Only native checkbox setting changes.');
$check($blocks[0]['attrs'] === ['unchanged' => true], 'Parent architecture unchanged.');
$first = $blocks;
ak_legal_demo_terms_checkbox($blocks);
$check($blocks === $first, 'Checkbox provisioning idempotent.');
$check(! str_contains(ak_legal_demo_css(), 'body.woocommerce-checkout'), 'Demo configuration cannot reintroduce competing checkout checkbox styles.');
$check(str_contains(ak_legal_demo_css(), '.ak-buyback-demo__privacy-check'), 'Existing Buyback demo presentation is preserved.');
echo "Legal demo provisioning tests passed: {$count} assertions.\n";
