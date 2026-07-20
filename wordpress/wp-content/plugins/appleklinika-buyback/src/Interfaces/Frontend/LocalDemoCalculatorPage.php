<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Frontend;

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\ServiceMode;
use AppleKlinika\Buyback\Domain\Pricing\ConditionAnswerCollection;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationInput;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult;
use AppleKlinika\Buyback\Domain\Pricing\PricingEngine;
use AppleKlinika\Buyback\Domain\Pricing\PricingModelKey;
use AppleKlinika\Buyback\Domain\Pricing\PricingOutcome;
use AppleKlinika\Buyback\Domain\Pricing\StorageCapacity;
use AppleKlinika\Buyback\Infrastructure\Inventory\WordPressDeviceCatalogReader;
use AppleKlinika\Buyback\Infrastructure\WordPress\LocalDemoSeeder;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressLocalDemoPageGateway;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressLocalDemoProductReader;

final class LocalDemoCalculatorPage
{
    private const NONCE_ACTION = 'ak_buyback_local_demo_calculate';
    private const NONCE_NAME = 'ak_buyback_local_demo_nonce';

    private const MODES = [
        ServiceMode::IN_STORE_INSTANT => [
            'title' => 'Azonnali személyes felvásárlás',
            'description' => 'Személyes átadás és bevizsgálás után, a lehető leggyorsabb helyi ügyintézéssel.',
            'process' => 'Személyes bevizsgálás',
        ],
        ServiceMode::FAST_ONLINE => [
            'title' => 'Gyors felvásárlás',
            'description' => 'Gyors feldolgozás és kifizetés a készülék beérkezése és bevizsgálása után.',
            'process' => 'Gyors ügyintézés',
        ],
        ServiceMode::HIGHER_OFFER => [
            'title' => 'Magasabb ajánlat',
            'description' => 'Magasabb előzetes összeg hosszabb, rugalmasabb feldolgozási idő mellett.',
            'process' => 'Részletes ellenőrzés',
        ],
        ServiceMode::TRADE_IN => [
            'title' => 'Azonnali beszámítás',
            'description' => 'A bevizsgálás után elfogadott összeg új készülék vásárlásába számítható be.',
            'process' => 'Beszámítás vásárláskor',
        ],
    ];

    public function __construct(
        private readonly RepositoryActivePriceBookResolver $resolver,
        private readonly PricingEngine $engine,
        private readonly WordPressDeviceCatalogReader $catalog,
        private readonly WordPressLocalDemoProductReader $products,
        private readonly LocalDemoQuestionnaire $questionnaire
    ) {
    }

    public function register(): void
    {
        add_shortcode('appleklinika_buyback_demo', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_filter('body_class', [$this, 'bodyClass']);
    }

    /** @param list<string> $classes @return list<string> */
    public function bodyClass(array $classes): array
    {
        if (is_page(WordPressLocalDemoPageGateway::SLUG)) {
            $classes[] = 'ak-buyback-demo-page';
        }

        return $classes;
    }

    public function enqueue(): void
    {
        if (! is_page(WordPressLocalDemoPageGateway::SLUG)) {
            return;
        }

        $cssPath = APPLEKLINIKA_BUYBACK_PATH . '/assets/css/local-demo.css';
        $jsPath = APPLEKLINIKA_BUYBACK_PATH . '/assets/js/local-demo.js';
        wp_enqueue_style('appleklinika-buyback-local-demo', APPLEKLINIKA_BUYBACK_URL . 'assets/css/local-demo.css', [], (string) filemtime($cssPath));
        wp_enqueue_script('appleklinika-buyback-local-demo', APPLEKLINIKA_BUYBACK_URL . 'assets/js/local-demo.js', [], (string) filemtime($jsPath), true);
    }

    public function render(): string
    {
        try {
            $resolved = $this->resolver->resolveForCurrencyAt(
                new CurrencyCode('HUF'),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
            if ($resolved->priceBook->label() !== LocalDemoSeeder::LABEL) {
                throw new \RuntimeException('A helyi demó árkönyve nem aktív.');
            }

            $labels = $this->modelLabels();
            $models = $this->buildModelCards(
                $resolved->priceBook,
                $resolved->enabledRules,
                $resolved->supportedConfigurations,
                $labels
            );
            $state = $this->requestState($resolved->supportedConfigurations);
        } catch (\Throwable $exception) {
            return '<div class="ak-buyback-demo"><div class="ak-buyback-demo__notice"><strong>HELYI DEMÓ</strong><p>'
                . esc_html($exception->getMessage())
                . '</p></div></div>';
        }

        $selectedModel = (string) $state['model_key'];
        $firstModelKey = array_key_first($models);
        $initialImage = $models[$selectedModel]['image_url'] ?? ($firstModelKey !== null ? ($models[$firstModelKey]['image_url'] ?? '') : '');
        $initialLabel = $labels[$selectedModel] ?? '';
        $initialPanel = $state['show_results'] ? 'offers' : $state['panel'];
        $flow = array_merge(['entry'], $this->questionnaire->panelOrder());

        ob_start();
        ?>
        <section
            class="ak-buyback-demo"
            data-initial-panel="<?php echo esc_attr($initialPanel); ?>"
            data-panel-order="<?php echo esc_attr((string) wp_json_encode($flow)); ?>"
        >
            <header class="ak-buyback-demo__header">
                <span class="ak-buyback-demo__badge">HELYI DEMÓ</span>
                <h2 class="ak-buyback-demo__title">Add el vagy számíttasd be Apple készüléked</h2>
                <p>Válaszolj néhány egyszerű kérdésre, és megmutatjuk a lehetséges előzetes ajánlatokat.</p>
            </header>

            <div class="ak-buyback-demo__navigation" aria-live="polite">
                <button class="ak-buyback-demo__back" type="button" data-demo-back aria-label="Vissza az előző kérdéshez">←</button>
                <div class="ak-buyback-demo__crumb" data-demo-crumb><?php echo esc_html($this->deviceBreadcrumb($initialLabel, (int) $state['storage_gb'])); ?></div>
                <div class="ak-buyback-demo__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="A kalkuláció előrehaladása"><span data-demo-progress></span></div>
                <span class="ak-buyback-demo__progress-text" data-demo-progress-text></span>
            </div>

            <?php if ($state['errors'] !== []) : ?>
                <div class="ak-buyback-demo__errors" role="alert" tabindex="-1">
                    <strong>Kérjük, ellenőrizd a válaszaidat.</strong>
                    <?php foreach ($state['errors'] as $error) : ?><p><?php echo esc_html($error); ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="ak-buyback-demo__form" method="post" novalidate data-demo-form>
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="ak_demo_action" value="calculate">

                <?php $this->renderEntryPanel(); ?>
                <?php $this->renderModelPanel($models, $state); ?>
                <?php $this->renderConfigurationPanel($models, $state, $initialImage); ?>
                <?php foreach ($this->questionnaire->panelOrder() as $panelKey) : ?>
                    <?php if (in_array($panelKey, ['model', 'configuration', 'offers', 'review'], true)) { continue; } ?>
                    <?php $this->renderQuestionnairePanel($panelKey, $state['answers'], $initialImage); ?>
                <?php endforeach; ?>
            </form>

            <?php if ($state['show_results']) : ?>
                <?php $this->renderResults($resolved->priceBook, $resolved->enabledRules, $state, $labels, $models); ?>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param list<\AppleKlinika\Buyback\Domain\Pricing\SupportedPriceConfiguration> $configurations
     * @return array{panel:string,model_key:string,storage_gb:int,answers:array<string,mixed>,errors:list<string>,show_results:bool}
     */
    private function requestState(array $configurations): array
    {
        $state = [
            'panel' => 'entry',
            'model_key' => '',
            'storage_gb' => 0,
            'answers' => $this->questionnaire->defaults(),
            'errors' => [],
            'show_results' => false,
        ];

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return $state;
        }

        $nonce = sanitize_text_field((string) wp_unslash($_POST[self::NONCE_NAME] ?? ''));
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            $state['errors'] = ['A biztonsági ellenőrzés sikertelen. Frissítsd az oldalt.'];
            return $state;
        }

        $state['model_key'] = sanitize_text_field((string) wp_unslash($_POST['model_key'] ?? ''));
        $state['storage_gb'] = (int) wp_unslash($_POST['storage_gb'] ?? 0);
        $rawAnswers = isset($_POST['questionnaire']) && is_array($_POST['questionnaire'])
            ? wp_unslash($_POST['questionnaire'])
            : [];

        $questionErrors = $this->questionnaire->validate($rawAnswers);
        $state['answers'] = $this->questionnaire->sanitize($rawAnswers);

        $eligibilityError = $this->questionnaire->eligibilityError($state['answers']);
        if ($eligibilityError !== null) {
            $state['errors'][] = $eligibilityError;
            $state['panel'] = 'configuration';
        }

        if (! $this->configurationExists($configurations, $state['model_key'], $state['storage_gb'])) {
            $state['errors'][] = 'Válassz egy elérhető iPhone modellt és tárhelyet.';
            $state['panel'] = $state['model_key'] === '' ? 'model' : 'configuration';
        }

        if ($questionErrors !== []) {
            foreach ($questionErrors as $error) {
                $state['errors'][] = $error;
            }
            $firstQuestionKey = array_key_first($questionErrors);
            if ($firstQuestionKey !== null) {
                $questions = $this->questionnaire->questions();
                $state['panel'] = (string) ($questions[$firstQuestionKey]['panel'] ?? 'liquid_contact');
            }
        }

        $state['errors'] = array_values(array_unique($state['errors']));
        $state['show_results'] = $state['errors'] === [];
        if ($state['show_results']) {
            $state['panel'] = 'offers';
        }

        return $state;
    }

    /** @param list<\AppleKlinika\Buyback\Domain\Pricing\SupportedPriceConfiguration> $configurations */
    private function configurationExists(array $configurations, string $modelKey, int $storage): bool
    {
        foreach ($configurations as $configuration) {
            if ($configuration->modelKey === $modelKey && $configuration->storageGb === $storage) {
                return true;
            }
        }

        return false;
    }

    private function renderEntryPanel(): void
    {
        ?>
        <section class="ak-buyback-demo__panel" data-demo-panel="entry" data-step-title="Készüléktípus">
            <div class="ak-buyback-demo__panel-heading">
                <span class="ak-buyback-demo__eyebrow">Kezdjük itt</span>
                <h3>Milyen Apple készüléked van?</h3>
                <p>Az iPhone felmérés már kipróbálható, a további kategóriák hamarosan érkeznek.</p>
            </div>
            <div class="ak-buyback-demo__category-grid">
                <?php foreach ([['iPhone', 'Telefon', true], ['iPad', 'Tablet', false], ['MacBook', 'Laptop', false], ['Apple Watch', 'Óra', false]] as [$title, $type, $active]) : ?>
                    <button class="ak-buyback-demo__category-card<?php echo $active ? ' is-available' : ''; ?>" type="button" <?php echo $active ? 'data-demo-next data-demo-target="model"' : 'disabled'; ?>>
                        <span class="ak-buyback-demo__category-icon" aria-hidden="true"><?php echo esc_html(mb_substr($title, 0, 1)); ?></span>
                        <strong><?php echo esc_html($title); ?></strong>
                        <small><?php echo esc_html($active ? $type . ' beszámítás' : 'Hamarosan'); ?></small>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="ak-buyback-demo__trust-grid" aria-label="A folyamat előnyei">
                <span>Átlátható előzetes ajánlat</span>
                <span>Személyes bevizsgálás</span>
                <span>Felvásárlás vagy beszámítás</span>
            </div>
        </section>
        <?php
    }

    /** @param array<string,array{label:string,image_url:string,storages:list<int>,teaser:?int}> $models @param array<string,mixed> $state */
    private function renderModelPanel(array $models, array $state): void
    {
        ?>
        <section class="ak-buyback-demo__panel" data-demo-panel="model" data-step-title="Modell kiválasztása" hidden>
            <div class="ak-buyback-demo__panel-heading"><span class="ak-buyback-demo__eyebrow">1. Készülék</span><h3>Válaszd ki az iPhone modelled</h3><p>Kereshetsz a modell nevére, vagy választhatsz a kártyák közül.</p></div>
            <label class="ak-buyback-demo__search"><span class="screen-reader-text">Modell keresése</span><input type="search" placeholder="Keresés az iPhone modellek között" data-model-search></label>
            <div class="ak-buyback-demo__device-grid" data-model-grid>
                <?php foreach ($models as $key => $model) : $id = 'ak-demo-model-' . sanitize_html_class($key); ?>
                    <label class="ak-buyback-demo__device-card" data-model-card data-search-text="<?php echo esc_attr(strtolower($model['label'])); ?>" data-image="<?php echo esc_url($model['image_url']); ?>" data-label="<?php echo esc_attr($model['label']); ?>" data-storages="<?php echo esc_attr(implode(',', $model['storages'])); ?>">
                        <input type="radio" id="<?php echo esc_attr($id); ?>" name="model_key" value="<?php echo esc_attr($key); ?>" <?php checked($state['model_key'], $key); ?> required>
                        <span class="ak-buyback-demo__device-image"><?php $this->renderImage($model['image_url'], $model['label']); ?></span>
                        <strong><?php echo esc_html($model['label']); ?></strong>
                        <?php if ($model['teaser'] !== null) : ?><small>Akár <?php echo esc_html($this->money($model['teaser'])); ?></small><?php endif; ?>
                        <span class="ak-buyback-demo__selected-mark">Kiválasztva</span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php $this->renderPanelActions('entry', 'configuration', 'Tovább a tárhelyhez'); ?>
        </section>
        <?php
    }

    /** @param array<string,array{label:string,image_url:string,storages:list<int>,teaser:?int}> $models @param array<string,mixed> $state */
    private function renderConfigurationPanel(array $models, array $state, string $initialImage): void
    {
        $allStorages = [];
        foreach ($models as $model) {
            foreach ($model['storages'] as $storage) {
                $allStorages[$storage] = $storage;
            }
        }
        sort($allStorages, SORT_NUMERIC);
        ?>
        <section class="ak-buyback-demo__panel" data-demo-panel="configuration" data-step-title="Konfiguráció" hidden>
            <div class="ak-buyback-demo__split">
                <?php $this->renderVisual($initialImage, 'Kiválasztott iPhone'); ?>
                <div class="ak-buyback-demo__question">
                    <span class="ak-buyback-demo__eyebrow">2. Konfiguráció</span>
                    <h3>Mekkora a készülék tárhelye?</h3>
                    <p>A tárhely méretét a Beállítások / Általános / Infó menüben ellenőrizheted.</p>
                    <div class="ak-buyback-demo__storage-grid">
                        <?php foreach ($allStorages as $storage) : $id = 'ak-demo-storage-' . $storage; ?>
                            <label class="ak-buyback-demo__choice-card ak-buyback-demo__choice-card--compact" data-storage-card data-storage="<?php echo esc_attr((string) $storage); ?>">
                                <input type="radio" id="<?php echo esc_attr($id); ?>" name="storage_gb" value="<?php echo esc_attr((string) $storage); ?>" <?php checked((string) $state['storage_gb'], (string) $storage); ?> required>
                                <strong><?php echo esc_html($this->storageLabel($storage)); ?></strong><span>Kiválasztás</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="ak-buyback-demo__configuration-divider"></div>
                    <?php $networkQuestion = $this->questionnaire->questions()['network_status']; ?>
                    <?php $this->renderQuestion('network_status', $networkQuestion, $state['answers']['network_status'] ?? $networkQuestion['default']); ?>
                    <?php $this->renderPanelActions('model', 'liquid_contact', 'Tovább az állapotfelméréshez'); ?>
                </div>
            </div>
            <div class="ak-buyback-demo__eligibility-modal" data-network-modal hidden role="dialog" aria-modal="true" aria-labelledby="ak-demo-network-title">
                <div class="ak-buyback-demo__eligibility-dialog">
                    <button type="button" class="ak-buyback-demo__modal-close" data-network-close aria-label="Bezárás">×</button>
                    <span class="ak-buyback-demo__modal-icon" aria-hidden="true">!</span>
                    <h4 id="ak-demo-network-title">Ezt a készüléket most nem tudjuk automatikusan értékelni</h4>
                    <p>A helyi demó jelenleg csak hálózatfüggetlen iPhone készülékekre ad előzetes ajánlatot. Lépj vissza, és módosítsd a választást.</p>
                    <button type="button" class="ak-buyback-demo__primary" data-network-close>Vissza a választáshoz</button>
                </div>
            </div>
        </section>
        <?php
    }

    /** @param array<string,mixed> $answers */
    private function renderQuestionnairePanel(string $panelKey, array $answers, string $initialImage): void
    {
        $panel = $this->questionnaire->panel($panelKey);
        $questions = $this->questionnaire->questionsForPanel($panelKey);
        $order = $this->questionnaire->panelOrder();
        $index = array_search($panelKey, $order, true);
        $previous = is_int($index) && $index > 0 ? $order[$index - 1] : 'entry';
        $next = is_int($index) && isset($order[$index + 1]) ? $order[$index + 1] : 'offers';
        ?>
        <section class="ak-buyback-demo__panel" data-demo-panel="<?php echo esc_attr($panelKey); ?>" data-step-title="<?php echo esc_attr($panel['short']); ?>" hidden>
            <div class="ak-buyback-demo__split">
                <?php $this->renderVisual($initialImage, 'A kiválasztott iPhone'); ?>
                <div class="ak-buyback-demo__question">
                    <span class="ak-buyback-demo__eyebrow"><?php echo esc_html($panel['step'] . '. ' . $panel['short']); ?></span>
                    <h3><?php echo esc_html($panel['title']); ?></h3>
                    <p><?php echo esc_html($this->panelIntro($panelKey)); ?></p>
                    <div class="ak-buyback-demo__question-stack">
                        <?php foreach ($questions as $key => $question) : ?>
                            <?php $this->renderQuestion((string) $key, $question, $answers[$key] ?? $question['default']); ?>
                        <?php endforeach; ?>
                    </div>
                    <?php $this->renderPanelActions($previous, $next, $next === 'offers' ? 'Előzetes ajánlatok' : 'Tovább', $next === 'offers'); ?>
                </div>
            </div>
        </section>
        <?php
    }

    /** @param array<string,mixed> $question */
    private function renderQuestion(string $key, array $question, mixed $value): void
    {
        if ($question['type'] === 'range') {
            $this->renderRangeQuestion($key, $question, (int) $value);
            return;
        }

        $type = (string) $question['type'];
        $isMulti = $type === 'multi';
        $selectedValues = $isMulti ? array_map('strval', (array) $value) : [(string) $value];
        $gridClass = count($question['options']) <= 3 ? ' ak-buyback-demo__choice-grid--two' : '';
        ?>
        <fieldset
            class="ak-buyback-demo__question-group"
            data-question
            data-question-key="<?php echo esc_attr($key); ?>"
            data-question-type="<?php echo esc_attr($type); ?>"
            <?php if ($isMulti) : ?>data-exclusive-value="<?php echo esc_attr((string) $question['exclusive']); ?>"<?php endif; ?>
        >
            <legend><?php echo esc_html((string) $question['label']); ?></legend>
            <?php if (! empty($question['helper'])) : ?><p class="ak-buyback-demo__question-helper"><?php echo esc_html((string) $question['helper']); ?></p><?php endif; ?>
            <div class="ak-buyback-demo__choice-grid<?php echo esc_attr($gridClass); ?>">
                <?php foreach ($question['options'] as $option => $meta) :
                    $id = 'ak-demo-' . sanitize_html_class($key . '-' . $option);
                    $checked = in_array((string) $option, $selectedValues, true);
                    ?>
                    <label class="ak-buyback-demo__choice-card" data-choice-key="<?php echo esc_attr($key); ?>" data-choice-value="<?php echo esc_attr((string) $option); ?>">
                        <input
                            id="<?php echo esc_attr($id); ?>"
                            type="<?php echo $isMulti ? 'checkbox' : 'radio'; ?>"
                            name="questionnaire[<?php echo esc_attr($key); ?>]<?php echo $isMulti ? '[]' : ''; ?>"
                            value="<?php echo esc_attr((string) $option); ?>"
                            <?php checked($checked); ?>
                            <?php echo $isMulti ? '' : 'required'; ?>
                        >
                        <strong><?php echo esc_html((string) $meta['label']); ?></strong>
                        <?php if (! empty($meta['helper'])) : ?><span><?php echo esc_html((string) $meta['helper']); ?></span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
    }

    /** @param array<string,mixed> $question */
    private function renderRangeQuestion(string $key, array $question, int $value): void
    {
        $rangeId = 'ak-demo-' . sanitize_html_class($key) . '-range';
        $numberId = 'ak-demo-' . sanitize_html_class($key) . '-number';
        ?>
        <div class="ak-buyback-demo__question-group ak-buyback-demo__battery" data-question data-question-key="<?php echo esc_attr($key); ?>" data-question-type="range">
            <h4><?php echo esc_html((string) $question['label']); ?></h4>
            <?php if (! empty($question['helper'])) : ?><p class="ak-buyback-demo__question-helper"><?php echo esc_html((string) $question['helper']); ?></p><?php endif; ?>
            <output for="<?php echo esc_attr($rangeId); ?>" data-battery-output><?php echo esc_html((string) $value); ?>%</output>
            <input id="<?php echo esc_attr($rangeId); ?>" type="range" min="<?php echo esc_attr((string) $question['min']); ?>" max="<?php echo esc_attr((string) $question['max']); ?>" step="1" value="<?php echo esc_attr((string) $value); ?>" aria-label="Akkumulátor állapota százalékban" data-battery-range>
            <label for="<?php echo esc_attr($numberId); ?>">Finomhangolás százalékban</label>
            <input id="<?php echo esc_attr($numberId); ?>" type="number" name="questionnaire[<?php echo esc_attr($key); ?>]" min="<?php echo esc_attr((string) $question['min']); ?>" max="<?php echo esc_attr((string) $question['max']); ?>" step="1" value="<?php echo esc_attr((string) $value); ?>" inputmode="numeric" data-battery-number required>
            <div class="ak-buyback-demo__battery-guidance"><span>90% felett</span><span>85–89%</span><span>80–84%</span><span>80% alatt: bevizsgálás</span></div>
        </div>
        <?php
    }

    /** @param \AppleKlinika\Buyback\Domain\Pricing\PriceBook $book @param list<\AppleKlinika\Buyback\Domain\Pricing\PricingRule> $rules @param array<string,mixed> $state @param array<string,string> $labels @param array<string,array{label:string,image_url:string,storages:list<int>,teaser:?int}> $models */
    private function renderResults($book, array $rules, array $state, array $labels, array $models): void
    {
        $questionnaireAnswers = $state['answers'];
        $canonicalAnswers = $this->questionnaire->mapToConditions($questionnaireAnswers);
        $manualReasons = $this->questionnaire->manualReviewReasons($questionnaireAnswers);
        $collection = ConditionAnswerCollection::fromAssociative($canonicalAnswers);
        $results = [];
        $highest = 0;

        foreach (array_keys(self::MODES) as $mode) {
            $serviceMode = new ServiceMode($mode);
            $result = $manualReasons !== []
                ? PricingCalculationResult::manualReview($book, $serviceMode, $manualReasons)
                : $this->engine->calculate(
                    $book,
                    $rules,
                    new PricingCalculationInput(
                        new DeviceCategory('iphone'),
                        new PricingModelKey($state['model_key']),
                        new StorageCapacity($state['storage_gb']),
                        $collection,
                        $serviceMode
                    )
                );
            $results[$mode] = $result;
            if ($result->outcome->code() === PricingOutcome::OFFERED) {
                $highest = max($highest, $result->finalAmount?->amount() ?? 0);
            }
        }

        $modelLabel = $labels[$state['model_key']] ?? $state['model_key'];
        $image = $models[$state['model_key']]['image_url'] ?? '';
        $storageLabel = $this->storageLabel($state['storage_gb']);
        $summary = $this->questionnaire->summary($questionnaireAnswers, $modelLabel, $storageLabel);
        ?>
        <section class="ak-buyback-demo__panel ak-buyback-demo__panel--offers" data-demo-panel="offers" data-step-title="Ajánlat" hidden>
            <div class="ak-buyback-demo__panel-heading">
                <span class="ak-buyback-demo__eyebrow">10. Ajánlat</span>
                <h3>Válaszd ki a számodra megfelelő lehetőséget</h3>
                <p><?php echo esc_html($modelLabel . ' · ' . $storageLabel); ?></p>
            </div>
            <div class="ak-buyback-demo__demo-notice">
                <strong>HELYI DEMÓ</strong>
                <span>Ez egy helyi, tesztelési célú előzetes ajánlat. Az összegek nem minősülnek végleges kereskedelmi ajánlatnak. A végleges összeget a készülék bevizsgálása után lehet meghatározni.</span>
            </div>
            <fieldset class="ak-buyback-demo__mode-fieldset" data-offer-group>
                <legend class="screen-reader-text">Ajánlattípus kiválasztása</legend>
                <div class="ak-buyback-demo__mode-grid">
                    <?php foreach (self::MODES as $mode => $meta) :
                        $result = $results[$mode];
                        $isOffered = $result->outcome->code() === PricingOutcome::OFFERED;
                        $amount = $isOffered ? ($result->finalAmount?->amount() ?? 0) : 0;
                        $isHighest = $isOffered && $amount === $highest;
                        $headline = $isOffered ? $this->money($amount) : $this->resultHeadline($result);
                        $description = $isOffered ? $meta['description'] : $this->safeReason($result);
                        $id = 'ak-demo-offer-' . sanitize_html_class($mode);
                        ?>
                        <label
                            class="ak-buyback-demo__mode-card<?php echo $mode === ServiceMode::FAST_ONLINE ? ' is-recommended' : ''; ?>"
                            data-mode-card
                            data-mode-code="<?php echo esc_attr($mode); ?>"
                            data-mode-title="<?php echo esc_attr($meta['title']); ?>"
                            data-mode-headline="<?php echo esc_attr($headline); ?>"
                            data-mode-description="<?php echo esc_attr($description); ?>"
                            data-mode-process="<?php echo esc_attr($meta['process']); ?>"
                        >
                            <input type="radio" id="<?php echo esc_attr($id); ?>" name="selected_offer_mode" value="<?php echo esc_attr($mode); ?>" data-mode-input>
                            <div class="ak-buyback-demo__mode-badges">
                                <?php if ($isHighest) : ?><span>Legmagasabb összeg</span><?php endif; ?>
                                <?php if ($mode === ServiceMode::FAST_ONLINE) : ?><span>Kényelmes választás</span><?php endif; ?>
                            </div>
                            <h4><?php echo esc_html($meta['title']); ?></h4>
                            <?php if ($isOffered) : ?>
                                <strong class="ak-buyback-demo__amount"><?php echo esc_html($headline); ?></strong>
                            <?php else : ?>
                                <strong class="ak-buyback-demo__review"><?php echo esc_html($headline); ?></strong>
                            <?php endif; ?>
                            <p><?php echo esc_html($description); ?></p>
                            <small><?php echo esc_html($meta['process']); ?></small>
                            <span class="ak-buyback-demo__mode-select">Ezt választom</span>
                            <?php if ($result->breakdown !== []) : ?>
                                <details>
                                    <summary>Számítás részletei</summary>
                                    <ul><?php foreach ($result->breakdown as $line) : ?><li><span><?php echo esc_html($line->publicLabel ?: $this->breakdownLabel($line->type)); ?></span><strong><?php echo esc_html($this->money($line->afterAmountMinor)); ?></strong></li><?php endforeach; ?></ul>
                                </details>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <p class="ak-buyback-demo__mode-message" data-mode-message hidden>Ebben a demóban a választás nem hoz létre felvásárlási kérelmet.</p>
            <div class="ak-buyback-demo__panel-actions">
                <button class="ak-buyback-demo__secondary" type="button" data-demo-back data-demo-target="other_defects">Vissza</button>
                <button class="ak-buyback-demo__primary" type="button" data-demo-next data-demo-target="review" data-offer-continue disabled>Tovább az összefoglalóhoz</button>
            </div>
        </section>

        <section class="ak-buyback-demo__panel ak-buyback-demo__panel--review" data-demo-panel="review" data-step-title="Összegzés" hidden>
            <div class="ak-buyback-demo__panel-heading">
                <span class="ak-buyback-demo__eyebrow">11. Összegzés</span>
                <h3>Ellenőrizd a megadott adatokat</h3>
                <p>Módosításhoz bármikor visszaléphetsz. A demó nem ment el valódi ajánlatkérést.</p>
            </div>
            <div class="ak-buyback-demo__review-layout">
                <article class="ak-buyback-demo__summary-card ak-buyback-demo__summary-card--device">
                    <h4>Készülék</h4>
                    <div class="ak-buyback-demo__summary-device">
                        <?php $this->renderImage($image, $modelLabel); ?>
                        <div><strong><?php echo esc_html($modelLabel); ?></strong><span><?php echo esc_html($storageLabel); ?></span></div>
                    </div>
                </article>
                <article class="ak-buyback-demo__summary-card ak-buyback-demo__summary-card--selected-offer">
                    <h4>Kiválasztott lehetőség</h4>
                    <strong data-review-mode-title>Nincs kiválasztva</strong>
                    <span class="ak-buyback-demo__review-amount" data-review-mode-headline>—</span>
                    <p data-review-mode-description>Válassz egy ajánlattípust az előző képernyőn.</p>
                    <small data-review-mode-process></small>
                </article>
                <article class="ak-buyback-demo__summary-card ak-buyback-demo__summary-card--answers">
                    <h4>Megadott válaszok</h4>
                    <?php foreach ($summary as $group => $rows) : ?>
                        <section class="ak-buyback-demo__summary-group">
                            <h5><?php echo esc_html($group); ?></h5>
                            <dl><?php foreach ($rows as $label => $answer) : ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($answer); ?></dd></div><?php endforeach; ?></dl>
                        </section>
                    <?php endforeach; ?>
                </article>
            </div>
            <div class="ak-buyback-demo__demo-notice">
                <strong>HELYI DEMÓ</strong>
                <span>A folytatás jelenleg csak bemutató. Nem jön létre felvásárlási kérelem, ügyféladat vagy rendelés.</span>
            </div>
            <div class="ak-buyback-demo__panel-actions">
                <button class="ak-buyback-demo__secondary" type="button" data-demo-back data-demo-target="offers">Vissza és módosítás</button>
                <a class="ak-buyback-demo__secondary ak-buyback-demo__primary--link" href="<?php echo esc_url(get_permalink()); ?>">Elölről kezdem</a>
                <button class="ak-buyback-demo__primary" type="button" data-demo-final-cta>Ajánlatkérés folytatása</button>
            </div>
            <p class="ak-buyback-demo__mode-message" data-final-message hidden>A valódi ajánlatkérés mentése egy későbbi fejlesztési lépésben készül el.</p>
        </section>
        <?php
    }

    /** @param \AppleKlinika\Buyback\Domain\Pricing\PriceBook $book @param list<\AppleKlinika\Buyback\Domain\Pricing\PricingRule> $rules @param list<\AppleKlinika\Buyback\Domain\Pricing\SupportedPriceConfiguration> $configurations @param array<string,string> $labels @return array<string,array{label:string,image_url:string,storages:list<int>,teaser:?int}> */
    private function buildModelCards($book, array $rules, array $configurations, array $labels): array
    {
        $frontend = $this->products->frontendModels();
        $models = [];
        $perfect = ConditionAnswerCollection::fromAssociative($this->perfectAnswers());
        foreach ($configurations as $configuration) {
            $key = $configuration->modelKey;
            if (! isset($models[$key])) {
                $models[$key] = [
                    'label' => $labels[$key] ?? $key,
                    'image_url' => $frontend[$key]['image_url'] ?? '',
                    'storages' => [],
                    'teaser' => null,
                ];
            }
            $models[$key]['storages'][$configuration->storageGb] = $configuration->storageGb;
            try {
                $result = $this->engine->calculate($book, $rules, new PricingCalculationInput(new DeviceCategory('iphone'), new PricingModelKey($key), new StorageCapacity($configuration->storageGb), $perfect, new ServiceMode(ServiceMode::HIGHER_OFFER)));
                if ($result->outcome->code() === PricingOutcome::OFFERED) {
                    $models[$key]['teaser'] = max((int) ($models[$key]['teaser'] ?? 0), $result->finalAmount?->amount() ?? 0);
                }
            } catch (\Throwable) {
                $models[$key]['teaser'] = null;
            }
        }
        foreach ($models as &$model) {
            $model['storages'] = array_values($model['storages']);
            sort($model['storages'], SORT_NUMERIC);
        }
        unset($model);
        uasort($models, static function (array $left, array $right): int {
            preg_match('/(\d+)/', $left['label'], $leftVersion);
            preg_match('/(\d+)/', $right['label'], $rightVersion);
            return ((int) ($rightVersion[1] ?? 0)) <=> ((int) ($leftVersion[1] ?? 0));
        });

        return $models;
    }

    /** @return array<string,int|bool|string> */
    private function perfectAnswers(): array
    {
        return [
            'battery_health' => 100,
            'powers_on' => true,
            'display_functional' => true,
            'touch_functional' => true,
            'face_id_functional' => true,
            'camera_functional' => true,
            'charging_functional' => true,
            'liquid_damage' => false,
            'motherboard_issue' => false,
            'screen_condition' => 'like_new',
            'frame_condition' => 'like_new',
            'back_glass_condition' => 'like_new',
            'camera_lens_condition' => 'like_new',
            'bent_or_dented' => false,
            'replacement_parts' => 'none_known',
        ];
    }

    private function panelIntro(string $panel): string
    {
        return match ($panel) {
            'liquid_contact' => 'A folyadékkal vagy párával való érintkezés akkor is fontos, ha a készülék jelenleg működik.',
            'screen_cosmetic' => 'Nézd meg alaposan a kijelzőt kikapcsolt és bekapcsolt állapotban, több szögből.',
            'frame_cosmetic' => 'A karcokat, festékkopást, ütődést és deformációt is vedd figyelembe.',
            'back_cosmetic' => 'A karcok mellett a lepattanásokat és az üveg repedéseit is ellenőrizd.',
            'battery' => 'Add meg az iPhone Beállításaiban látható maximális kapacitást.',
            'display_defects' => 'A külső állapottól függetlenül több működési kijelzőhibát is megjelölhetsz.',
            'other_defects' => 'Jelöld meg az összes olyan funkciót, amely nem működik megfelelően.',
            default => 'Válaszd ki a készülékedre leginkább jellemző választ.',
        };
    }

    private function renderVisual(string $image, string $alt): void
    {
        echo '<div class="ak-buyback-demo__visual" aria-hidden="true"><div class="ak-buyback-demo__visual-image" data-demo-visual>';
        echo '<img src="' . esc_url($image) . '" alt="' . esc_attr($alt) . '" loading="lazy" data-demo-device-image' . ($image === '' ? ' hidden' : '') . '>';
        echo '<span class="ak-buyback-demo__image-fallback" data-demo-device-fallback' . ($image !== '' ? ' hidden' : '') . '><span></span>iPhone</span>';
        echo '</div><strong data-demo-visual-label>A kiválasztott iPhone</strong><span>Az illusztráció a katalógus valódi termékképe.</span></div>';
    }

    private function renderImage(string $url, string $alt): void
    {
        if ($url !== '') {
            echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy">';
            return;
        }

        echo '<span class="ak-buyback-demo__image-fallback"><span></span>iPhone</span>';
    }

    private function renderPanelActions(string $back, string $next, string $nextLabel, bool $submit = false): void
    {
        echo '<div class="ak-buyback-demo__panel-actions">';
        echo '<button class="ak-buyback-demo__secondary" type="button" data-demo-back data-demo-target="' . esc_attr($back) . '">Vissza</button>';
        if ($submit) {
            echo '<button class="ak-buyback-demo__primary" type="submit">' . esc_html($nextLabel) . '</button>';
        } else {
            echo '<button class="ak-buyback-demo__primary" type="button" data-demo-next data-demo-target="' . esc_attr($next) . '">' . esc_html($nextLabel) . '</button>';
        }
        echo '</div>';
    }

    private function safeReason(PricingCalculationResult $result): string
    {
        foreach ($result->matchedRules as $rule) {
            if ($rule->publicLabel !== null) {
                return $rule->publicLabel . '. A pontos összeghez bevizsgálás szükséges.';
            }
        }

        $knownReasons = [
            'below_minimum_offer' => 'A számított összeg a beállított minimum alatt van, ezért személyes ellenőrzés szükséges.',
            'missing_base_price' => 'Ehhez a modellhez és tárhelyhez nincs használható alapár.',
            'duplicate_base_price' => 'Az árkönyvben egymásnak ellentmondó alapárak találhatók.',
            'duplicate_mode_adjustment' => 'Az árkönyvben egymásnak ellentmondó ajánlattípus-szabályok találhatók.',
        ];
        $messages = [];
        foreach ($result->reasonCodes as $reason) {
            $messages[] = $knownReasons[$reason] ?? (str_contains($reason, ' ') ? $reason : 'A megadott állapot egyedi bevizsgálást igényel.');
        }

        return $messages !== []
            ? implode(' ', array_values(array_unique($messages)))
            : 'A megadott állapot alapján bevizsgálás szükséges.';
    }

    private function resultHeadline(PricingCalculationResult $result): string
    {
        return match ($result->outcome->code()) {
            PricingOutcome::REJECTED => 'Automatikus ajánlat nem adható',
            PricingOutcome::CONFIGURATION_ERROR => 'Az árkönyv ellenőrzése szükséges',
            default => 'Személyes ellenőrzés szükséges',
        };
    }

    private function breakdownLabel(string $type): string
    {
        return match ($type) {
            'base_price' => 'Helyi demó alapár',
            'rounding' => 'Kerekítés',
            'mode_adjustment' => 'Átadási mód módosítása',
            default => 'Állapot szerinti módosítás',
        };
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' Ft';
    }

    private function storageLabel(int $storage): string
    {
        return $storage === 1024 ? '1 TB' : $storage . ' GB';
    }

    private function deviceBreadcrumb(string $model, int $storage): string
    {
        if ($model === '') {
            return 'Készülék kiválasztása';
        }

        return $model . ($storage > 0 ? ' · ' . $this->storageLabel($storage) : '');
    }

    /** @return array<string,string> */
    private function modelLabels(): array
    {
        $labels = [];
        foreach ($this->catalog->iPhoneModels() as $item) {
            $labels[$item->modelKey] = $item->label;
        }

        return $labels;
    }
}
