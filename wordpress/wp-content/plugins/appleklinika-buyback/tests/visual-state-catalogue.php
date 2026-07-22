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

$fallback = $catalogue->fallback();
$runner->assert($fallback['visual_key'] === 'device/fallback' && is_file(APPLEKLINIKA_BUYBACK_PATH . '/' . $fallback['fallback_path']), 'Unknown visual keys resolve to a safe neutral fallback');

$inputProperties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(PricingCalculationInput::class))->getProperties());
$runner->assert(! in_array('visualKey', $inputProperties, true) && ! in_array('visual_key', $inputProperties, true), 'Visual metadata never enters PricingCalculationInput');

$javascript = (string) file_get_contents(APPLEKLINIKA_BUYBACK_PATH . '/assets/js/local-demo.js');
$runner->assert(! str_contains($javascript, 'screen/flawless') && ! str_contains($javascript, 'back-glass/cracked'), 'JavaScript contains no duplicated production visual mapping');
$runner->assert(str_contains($javascript, 'visualCatalogue') && str_contains($javascript, 'fallback_url'), 'JavaScript consumes the server-generated catalogue payload and safe fallback');
$runner->assert(! str_contains($javascript, 'data-visual-assets-base'), 'JavaScript no longer constructs visual asset paths itself');

$runner->finish();
