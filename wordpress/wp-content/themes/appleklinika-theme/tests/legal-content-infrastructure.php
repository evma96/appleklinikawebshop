<?php

declare(strict_types=1);

final class LegalContentInfrastructureTest
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
            fwrite(STDERR, implode("\n", array_map(static fn (string $failure): string => "FAIL: {$failure}", $this->failures)) . "\n");
            exit(1);
        }

        echo "Legal content infrastructure tests passed: {$this->assertions} assertions.\n";
        exit(0);
    }
}

$themeRoot = dirname(__DIR__);
$legal = file_get_contents($themeRoot . '/inc/legal-documents.php');
$theme = file_get_contents($themeRoot . '/functions.php');
$buybackRoot = dirname($themeRoot, 2) . '/plugins/appleklinika-buyback';
$submission = file_get_contents($buybackRoot . '/src/Application/PublicRequest/PublicBuybackRequestSubmission.php');
$publicPage = file_get_contents($buybackRoot . '/src/Interfaces/Frontend/LocalDemoCalculatorPage.php');
$test = new LegalContentInfrastructureTest();

$test->assert(is_string($legal) && str_contains($legal, "'terms'") && str_contains($legal, "'privacy'") && str_contains($legal, "'cookies'") && str_contains($legal, "'withdrawal'") && str_contains($legal, "'warranty'") && str_contains($legal, "'shipping_payment'") && str_contains($legal, "'marketing'") && str_contains($legal, "'buyback_terms'"), 'One central registry defines all eight legal page locations.');
$test->assert(is_string($legal) && str_contains($legal, "post_status === 'publish'") && str_contains($legal, 'available'), 'Only configured published pages resolve to public links.');
$test->assert(is_string($theme) && str_contains($theme, 'appleklinika_legal_public_documents()') && str_contains($theme, 'Jogi információk') && ! str_contains($theme, "'ÁSZF' => 'aszf'"), 'Footer uses the central legal registry and no longer hard-codes legal page slugs.');
$test->assert(is_string($legal) && str_contains($legal, 'woocommerce_get_terms_and_conditions_checkbox_text') && str_contains($legal, 'woocommerce_get_privacy_policy_text'), 'Checkout terms and privacy references reuse WooCommerce native mechanisms.');
$test->assert(is_string($theme) && str_contains($theme, "'appleklinika/marketing_consent'") && str_contains($theme, "'required' => false") && str_contains($theme, "appleklinika_marketing_consent"), 'Marketing consent is an optional checkout field that can be stored without blocking purchase.');
$test->assert(is_string($legal) && str_contains($legal, 'woocommerce_register_form') && str_contains($legal, 'appleklinika_marketing_consent'), 'Registration receives the same optional, unchecked marketing consent only when configured.');
$test->assert(is_string($submission) && str_contains($submission, 'terms_acknowledged') && str_contains($submission, 'PublicBuybackSubmissionException'), 'Buyback terms acceptance is enforced server-side.');
$test->assert(is_string($publicPage) && str_contains($publicPage, "name=\"terms_acknowledged\"") && str_contains($publicPage, 'Felvásárlási feltételek') && str_contains($publicPage, 'appleklinika_legal_marketing_document_available'), 'Buyback submission renders central legal links and a separate optional marketing control.');
$test->assert(is_string($theme) && ! str_contains($theme, 'indulás előtti, szerkeszthető ÁSZF tartalom') && ! str_contains($theme, 'indulás előtti, szerkeszthető adatvédelmi tájékoztató minta'), 'Theme source no longer seeds demo legal copy as public content.');
$test->finish();
