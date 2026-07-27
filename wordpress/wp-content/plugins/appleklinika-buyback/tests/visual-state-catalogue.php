<?php

declare(strict_types=1);

$wordpressRoot = dirname(__DIR__, 4);
require_once $wordpressRoot . '/wp-load.php';

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\LocalDemo\VisualStateCatalogue;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;

final class VisualStateCatalogueTestRunner
{
    private int $assertions = 0;
    /** @var list<string> */
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    public function finish(): never
    {
        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                fwrite(STDERR, "FAIL: {$failure}\n");
            }
            fwrite(STDERR, sprintf("%d assertion(s), %d failure(s).\n", $this->assertions, count($this->failures)));
            exit(1);
        }

        echo sprintf("Buyback visual-state catalogue tests passed: %d assertions.\n", $this->assertions);
        exit(0);
    }
}

$runner = new VisualStateCatalogueTestRunner();
$questionnaire = new LocalDemoQuestionnaire();
$catalogue = new VisualStateCatalogue($questionnaire);
$entries = $catalogue->entries();
$states = $questionnaire->visualStateAnswers();

$runner->assert(count($states) === 15, 'The public questionnaire exposes every production visual answer');
$runner->assert(count($entries) === count($states), 'Every public visual key has exactly one canonical catalogue entry');

foreach ($states as $state) {
    $entry = $entries[$state['visual_key']] ?? null;
    $runner->assert($entry !== null, 'Every questionnaire visual key resolves through the catalogue: ' . $state['visual_key']);
    $runner->assert($entry !== null && $entry['question_key'] === $state['question_key'] && $entry['answer_key'] === $state['answer_key'], 'Catalogue retains the canonical question and answer source');
    $runner->assert($entry !== null && $entry['expected_path'] === 'assets/images/buyback-states/' . $state['visual_key'] . '.webp', 'Final asset path is derived from the canonical visual key');
    $runner->assert($entry !== null && is_file(APPLEKLINIKA_BUYBACK_PATH . '/' . $entry['fallback_path']), 'Every final state has an existing Apple Klinika fallback');
}

$approvedFinalAssets = [
    'screen/flawless' => ['label' => 'Hibátlan', 'hash' => 'c46d804941202f9562adecfead97c6281e8744117f6148ba4e7d6511f794df04'],
    'screen/minor-wear' => ['label' => 'Apró használati nyomok', 'hash' => '67c48ef502df7f5dd6f2f0ae4e283e950a5bb107f63cbe9067a6e2ffeb3872ab'],
    'screen/heavier-wear' => ['label' => 'Intenzívebb használati nyomok', 'hash' => '834bacc754f4dace61c289fea29840575faeb9360e68a692b786ac51e8313858'],
    'screen/strongly-worn' => ['label' => 'Erősen kopott', 'hash' => '10435ae236d2ead9ebc4fb1d25ad90724b839a24f6e2e36648398b3ceb8268f9'],
    'screen/cracked' => ['label' => 'Törött vagy repedt', 'hash' => 'ff32444806d2bdbab50390bd7146a7cdda3573b5b80d48776af0bc534122125f'],
    'back-glass/flawless' => ['label' => 'Hibátlan', 'hash' => 'e8fe2e8b9c47cec25bbd3460e2d0ff8e90e797fdd538886e64b7264ecededc02'],
    'back-glass/minor-wear' => ['label' => 'Apró használati nyomok', 'hash' => '679249c563d36607cc45266bdc01f713d4d1602695e989005e4ab0cf3ef37f2f'],
    'back-glass/heavier-wear' => ['label' => 'Intenzívebb használati nyomok', 'hash' => '4faca1cb596fdbeebb6a471aac9f77b418d46bd3cb78206faea164f69c2926d0'],
    'back-glass/strongly-worn' => ['label' => 'Erősen használt', 'hash' => '9c0b57b3f502e95f0770ea7c0ffd7d125eceb6891ec1aae3852b7031fc859c14'],
    'back-glass/cracked' => ['label' => 'Törött vagy repedt', 'hash' => 'bc3054d36605acdd15e5bc2fc12e150806ed674c415d7c188fd55f3ad04930f9'],
];
$finalDimensions = [];
foreach ($approvedFinalAssets as $visualKey => $approved) {
    $entry = $entries[$visualKey] ?? null;
    $path = $entry === null ? '' : APPLEKLINIKA_BUYBACK_PATH . '/' . $entry['expected_path'];
    $image = $path === '' ? false : getimagesize($path);

    $runner->assert($entry !== null && $entry['answer_label'] === $approved['label'], 'Approved final asset keeps its canonical public label: ' . $visualKey);
    $runner->assert($entry !== null && is_file($path) && is_array($image) && ($image['mime'] ?? '') === 'image/webp', 'Approved final asset exists and is readable as WebP: ' . $visualKey);
    $runner->assert($entry !== null && $entry['expected_path'] === 'assets/images/buyback-states/' . $visualKey . '.webp' && ! str_ends_with($entry['expected_path'], '.svg'), 'Approved final state resolves to its final WebP rather than an SVG fallback: ' . $visualKey);
    $runner->assert(is_file($path) && hash_file('sha256', $path) === $approved['hash'], 'Approved final asset bytes match the archive hash: ' . $visualKey);
    if (is_array($image)) {
        $finalDimensions[$visualKey] = [(int) $image[0], (int) $image[1]];
    }
}
$runner->assert($finalDimensions === array_fill_keys(array_keys($approvedFinalAssets), [620, 1240]), 'All ten normalized final assets retain the identical 620×1240 canvas');

$runner->assert(function_exists('imagecreatefromwebp'), 'The focused asset test can decode WebP alpha data through GD');
foreach (array_keys($approvedFinalAssets) as $visualKey) {
    $entry = $entries[$visualKey] ?? null;
    $path = $entry === null ? '' : APPLEKLINIKA_BUYBACK_PATH . '/' . $entry['expected_path'];
    $webp = $path !== '' && function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false;
    $hasTransparentPixel = false;
    $hasPartiallyTransparentPixel = false;

    if ($webp !== false) {
        for ($y = 0; $y < imagesy($webp) && ! ($hasTransparentPixel && $hasPartiallyTransparentPixel); ++$y) {
            for ($x = 0; $x < imagesx($webp); ++$x) {
                $alpha = (imagecolorat($webp, $x, $y) >> 24) & 0x7f;
                $hasTransparentPixel = $hasTransparentPixel || $alpha === 127;
                $hasPartiallyTransparentPixel = $hasPartiallyTransparentPixel || ($alpha > 0 && $alpha < 127);
                if ($hasTransparentPixel && $hasPartiallyTransparentPixel) {
                    break;
                }
            }
        }
        imagedestroy($webp);
    }

    $runner->assert($hasTransparentPixel && $hasPartiallyTransparentPixel, 'Approved final asset retains fully and partially transparent pixels: ' . $visualKey);
}

$approvedFrameAssets = [
    'frame/flawless' => ['label' => 'Hibátlan', 'hash' => '8367e1c95396b6863c335776c9b241e6f3e4fdafea8ae2424467fd9c0b1bc376'],
    'frame/minor-wear' => ['label' => 'Apró használati nyomok', 'hash' => '19066f3dababdf6c5449c538a928d4da8d09e67b4cef84d5f24c00b8f95c6f58'],
    'frame/heavier-wear' => ['label' => 'Intenzívebb használati nyomok', 'hash' => 'f36ddb62b3594b14124671895241e5712e84c10ab56d82ad935df4069d293124'],
    'frame/strongly-worn' => ['label' => 'Erősen használt', 'hash' => '677268b440875336173fcc952938fea03f2705e569e3b52ce7a26625910c3a4b'],
    'frame/damaged' => ['label' => 'Sérült vagy deformált', 'hash' => '378ed109bf9a0bcf9cc399f7c7dd17a12a270fc0d36e8d1ef48619ba8a929bb1'],
];
$frameDimensions = [];
foreach ($approvedFrameAssets as $visualKey => $approved) {
    $entry = $entries[$visualKey] ?? null;
    $path = $entry === null ? '' : APPLEKLINIKA_BUYBACK_PATH . '/' . $entry['expected_path'];
    $image = $path === '' ? false : getimagesize($path);
    $webp = $path !== '' && function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false;
    $hasTransparentPixel = false;
    $hasPartiallyTransparentPixel = false;

    $runner->assert($entry !== null && $entry['answer_label'] === $approved['label'], 'Approved frame asset keeps its canonical public label: ' . $visualKey);
    $runner->assert($entry !== null && is_file($path) && is_array($image) && ($image['mime'] ?? '') === 'image/webp', 'Approved frame asset exists and is readable as WebP: ' . $visualKey);
    $runner->assert($entry !== null && $entry['expected_path'] === 'assets/images/buyback-states/' . $visualKey . '.webp' && ! str_ends_with($entry['expected_path'], '.svg'), 'Approved frame state resolves to its final WebP rather than an SVG fallback: ' . $visualKey);
    $runner->assert(is_file($path) && hash_file('sha256', $path) === $approved['hash'], 'Approved frame asset bytes match the archive hash: ' . $visualKey);

    if (is_array($image)) {
        $frameDimensions[$visualKey] = [(int) $image[0], (int) $image[1]];
    }

    if ($webp !== false) {
        for ($y = 0; $y < imagesy($webp) && ! ($hasTransparentPixel && $hasPartiallyTransparentPixel); ++$y) {
            for ($x = 0; $x < imagesx($webp); ++$x) {
                $alpha = (imagecolorat($webp, $x, $y) >> 24) & 0x7f;
                $hasTransparentPixel = $hasTransparentPixel || $alpha === 127;
                $hasPartiallyTransparentPixel = $hasPartiallyTransparentPixel || ($alpha > 0 && $alpha < 127);
                if ($hasTransparentPixel && $hasPartiallyTransparentPixel) {
                    break;
                }
            }
        }
        imagedestroy($webp);
    }

    $runner->assert($hasTransparentPixel && $hasPartiallyTransparentPixel, 'Approved frame asset retains fully and partially transparent pixels: ' . $visualKey);
}
$runner->assert($frameDimensions === array_fill_keys(array_keys($approvedFrameAssets), [971, 1619]), 'All five approved frame assets retain the identical 971×1619 canvas');

$fallback = $catalogue->fallback();
$runner->assert($fallback['visual_key'] === 'device/fallback' && is_file(APPLEKLINIKA_BUYBACK_PATH . '/' . $fallback['fallback_path']), 'Unknown visual keys resolve to a safe neutral fallback');

$inputProperties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(PricingCalculationInput::class))->getProperties());
$runner->assert(! in_array('visualKey', $inputProperties, true) && ! in_array('visual_key', $inputProperties, true), 'Visual metadata never enters PricingCalculationInput');

$javascript = (string) file_get_contents(APPLEKLINIKA_BUYBACK_PATH . '/assets/js/local-demo.js');
$runner->assert(! str_contains($javascript, 'screen/flawless') && ! str_contains($javascript, 'back-glass/cracked'), 'JavaScript contains no duplicated production visual mapping');
$runner->assert(str_contains($javascript, 'visualCatalogue') && str_contains($javascript, 'fallback_url'), 'JavaScript consumes the server-generated catalogue payload and safe fallback');
$runner->assert(! str_contains($javascript, 'data-visual-assets-base'), 'JavaScript no longer constructs visual asset paths itself');
$runner->assert(str_contains($javascript, "document.body.classList.toggle('ak-buyback-wizard-active'") && str_contains($javascript, "['entry', 'offers', 'review']"), 'The page-specific contained-wizard state is only enabled for active questionnaire panels');
$runner->assert(str_contains($javascript, 'syncModelProgression') && str_contains($javascript, "button.disabled = !selectedModel()"), 'Model progression follows the real selected model state');
$runner->assert(str_contains($javascript, 'syncSingleChoiceDescriptions') && str_contains($javascript, "group.dataset.questionType !== 'single'") && str_contains($javascript, "description.hidden = !expanded"), 'Only single-select answer descriptions are progressively disclosed');
$runner->assert(str_contains($javascript, 'syncStorefrontHeader') && str_contains($javascript, "'Vissza a webshopba'"), 'The active wizard changes only the storefront logo label into an exit action');
$runner->assert(str_contains($javascript, "document.body.classList.toggle('ak-buyback-entry-active', current === 'entry')") && str_contains($javascript, "next.matches('[data-entry-family]')"), 'Entry and active-wizard page states remain explicit and the supported family card is keyboard-operable');
$runner->assert(str_contains($javascript, "document.body.classList.toggle('ak-buyback-offer-active', current === 'offers')") && str_contains($javascript, 'data-offer-summary-selection'), 'The Offer page keeps the compact header and updates its selected-offer summary row');
$runner->assert(str_contains($javascript, 'modelNoResults.hidden = visibleCount !== 0') && str_contains($javascript, 'modelGrid.scrollTop = 0') && str_contains($javascript, 'card.hidden = !visible'), 'Model filtering hides complete cards, shows a no-result state, and resets the grid scroll position');
$stylesheet = (string) file_get_contents(APPLEKLINIKA_BUYBACK_PATH . '/assets/css/local-demo.css');
$runner->assert(str_contains($stylesheet, 'body.ak-buyback-demo-page.ak-buyback-wizard-active header.wp-block-template-part') && str_contains($stylesheet, '.ak-product-search') && str_contains($stylesheet, '.ak-category-nav'), 'The compact storefront header is scoped to the active Buyback wizard state');
$runner->assert(str_contains($stylesheet, 'width: 100%;') && str_contains($stylesheet, 'justify-self: center;'), 'The model search remains full width inside the contained wizard');
$runner->assert(str_contains($stylesheet, '.ak-buyback-demo__panel[data-demo-panel="model"][hidden]'), 'An inactive model panel remains hidden while a questionnaire step is active');
$runner->assert(str_contains($stylesheet, '.ak-buyback-demo__choice-grid--liquid-contact') && str_contains($stylesheet, '.ak-buyback-demo__customer-summary'), 'Liquid-contact and the Offer review summary have focused presentation rules');
$runner->assert(str_contains($stylesheet, '.ak-buyback-demo__device-media') && str_contains($stylesheet, '.ak-buyback-demo__model-media-image') && str_contains($stylesheet, 'height: 100% !important') && str_contains($stylesheet, 'overflow: hidden'), 'Model media has a dedicated clipping frame with an explicitly contained image');
$runner->assert(str_contains($stylesheet, '.ak-buyback-demo__entry-family') && str_contains($stylesheet, '.ak-buyback-demo__entry-benefits') && str_contains($stylesheet, 'repeat(auto-fit, minmax(200px, 280px))'), 'Entry and scalable model-catalogue presentation are scoped to the Buyback flow');
$runner->assert(str_contains($stylesheet, '.ak-buyback-demo__visual-image') && str_contains($stylesheet, 'background: transparent;') && str_contains($stylesheet, 'border: 0;') && str_contains($stylesheet, 'min-height: 0 !important;') && str_contains($stylesheet, 'padding: 0;'), 'Transparent condition assets use the full visual frame without an inner media tile or image padding');

$runner->finish();
