<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Frontend;

use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\LocalDemo\VisualStateCatalogue;
use AppleKlinika\Buyback\Application\Pricing\RepositoryActivePriceBookResolver;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackRequestSubmission;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackSubmissionException;
use AppleKlinika\Buyback\Application\PublicRequest\DispatchBuybackRequestNotifications;
use AppleKlinika\Buyback\Domain\Buyback\DeviceCategory;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeDefinition;
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
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressLocalDemoPageGateway;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressLocalDemoProductReader;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\WordPressPublicBuybackRequestStore;
use AppleKlinika\Buyback\Infrastructure\WordPress\WordPressBuybackRequestMailer;

final class LocalDemoCalculatorPage
{
    private const NONCE_ACTION = 'ak_buyback_local_demo_calculate';
    private const NONCE_NAME = 'ak_buyback_local_demo_nonce';
    private const SUBMISSION_NONCE_ACTION = 'ak_buyback_public_request_submit';
    private const SUBMISSION_NONCE_NAME = 'ak_buyback_public_request_nonce';

    public function __construct(
        private readonly RepositoryActivePriceBookResolver $resolver,
        private readonly PricingEngine $engine,
        private readonly WordPressDeviceCatalogReader $catalog,
        private readonly WordPressLocalDemoProductReader $products,
        private readonly LocalDemoQuestionnaire $questionnaire,
        private readonly ?PublicBuybackRequestSubmission $submission = null,
        private readonly ?WordPressPublicBuybackRequestStore $publicStore = null,
        private readonly ?DispatchBuybackRequestNotifications $notifications = null,
        private readonly ?WordPressBuybackRequestMailer $mailer = null
    ) {
    }

    public function register(): void
    {
        add_shortcode('appleklinika_buyback_demo', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_filter('body_class', [$this, 'bodyClass']);
        add_action('template_redirect', [$this, 'handleSubmission']);
    }

    public function registerMailTransport(): void
    {
        $this->mailer?->register();
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
        wp_enqueue_style('appleklinika-buyback-local-demo', APPLEKLINIKA_BUYBACK_URL . 'assets/css/local-demo.css', [], md5_file($cssPath) ?: null);
        wp_enqueue_script('appleklinika-buyback-local-demo', APPLEKLINIKA_BUYBACK_URL . 'assets/js/local-demo.js', [], md5_file($jsPath) ?: null, true);
    }

    public function handleSubmission(): void
    {
        if (! is_page(WordPressLocalDemoPageGateway::SLUG) || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || (string) ($_POST['ak_buyback_action'] ?? '') !== 'submit_request') {
            return;
        }
        if ($this->submission === null || $this->publicStore === null) {
            $this->redirectWithSubmissionError('A felvásárlási igény beküldése átmenetileg nem érhető el.');
        }

        $nonce = sanitize_text_field((string) wp_unslash($_POST[self::SUBMISSION_NONCE_NAME] ?? ''));
        if (! wp_verify_nonce($nonce, self::SUBMISSION_NONCE_ACTION)) {
            $this->redirectWithSubmissionError('A biztonsági ellenőrzés sikertelen. Frissítsd az oldalt, majd számold újra az ajánlatot.');
        }
        if (trim((string) wp_unslash($_POST['website'] ?? '')) !== '') {
            $this->redirectWithSubmissionError('A felvásárlási igény nem küldhető el.');
        }
        $startedAt = (int) wp_unslash($_POST['form_started_at'] ?? 0);
        if ($startedAt <= 0 || time() - $startedAt < 3) {
            $this->redirectWithSubmissionError('Kérjük, töltsd ki az űrlapot, majd próbáld újra.');
        }
        $rateKey = 'ak_buyback_submit_' . hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), wp_salt('nonce'));
        if ((int) get_transient($rateKey) >= 5) {
            $this->redirectWithSubmissionError('Túl sok beküldési kísérlet történt. Kérjük, próbáld újra később.');
        }

        $raw = wp_unslash($_POST);
        $input = [
            'idempotency_token' => sanitize_text_field((string) ($raw['idempotency_token'] ?? '')),
            'full_name' => preg_replace('/\s+/u', ' ', sanitize_text_field((string) ($raw['full_name'] ?? ''))) ?? '',
            'email' => strtolower(sanitize_email((string) ($raw['email'] ?? ''))),
            'phone' => trim(preg_replace('/\s+/', ' ', preg_replace('/[^+0-9 ()-]/', '', (string) ($raw['phone'] ?? '')) ?? '') ?? ''),
            'customer_note' => sanitize_textarea_field((string) ($raw['customer_note'] ?? '')),
            'privacy_acknowledged' => isset($raw['privacy_acknowledged']) && (string) $raw['privacy_acknowledged'] === '1',
            'model_key' => sanitize_key((string) ($raw['model_key'] ?? '')),
            'storage_gb' => (int) ($raw['storage_gb'] ?? 0),
            'color_key' => sanitize_key((string) ($raw['color_key'] ?? '')),
            'selected_offer_mode' => sanitize_key((string) ($raw['selected_offer_mode'] ?? '')),
            'manual_review_requested' => isset($raw['manual_review_requested']) && (string) $raw['manual_review_requested'] === '1',
            'price_book_id' => (int) ($raw['price_book_id'] ?? 0),
            'price_book_version' => (int) ($raw['price_book_version'] ?? 0),
            'questionnaire' => isset($raw['questionnaire']) && is_array($raw['questionnaire']) ? $raw['questionnaire'] : [],
            'privacy_url' => $this->privacyNotice()['url'],
            'privacy_marker' => $this->privacyNotice()['marker'],
        ];

        try {
            $result = $this->submission->submit($input, $this->catalog->iPhoneCatalog());
            set_transient('ak_buyback_success_' . $input['idempotency_token'], [
                'request_number' => $result->requestNumber,
                'device' => $result->device,
                'service_mode' => $result->serviceMode,
                'amount_minor' => $result->amountMinor,
                'manual_review' => $result->manualReview,
                'manual_review_reasons' => $result->manualReviewReasons,
            ], HOUR_IN_SECONDS * 2);
            $this->notifications?->dispatch($result, $input);
            set_transient($rateKey, (int) get_transient($rateKey) + 1, HOUR_IN_SECONDS);
            wp_safe_redirect(add_query_arg('ak_buyback_success', rawurlencode($input['idempotency_token']), get_permalink()));
            exit;
        } catch (PublicBuybackSubmissionException $exception) {
            $this->redirectWithSubmissionError($exception->getMessage());
        }
    }

    private function redirectWithSubmissionError(string $message): never
    {
        set_transient('ak_buyback_submission_error_' . wp_generate_uuid4(), $message, MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('ak_buyback_submission_error', rawurlencode($message), get_permalink()));
        exit;
    }

    public function render(): string
    {
        $successToken = sanitize_text_field((string) ($_GET['ak_buyback_success'] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/', $successToken) === 1) {
            $success = get_transient('ak_buyback_success_' . $successToken);
            if (is_array($success)) {
                return $this->renderSuccess($success);
            }
        }
        try {
            $resolved = $this->resolver->resolveForCurrencyAt(
                new CurrencyCode('HUF'),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
            $catalog = $this->catalog->iPhoneCatalog();
            $labels = $this->modelLabels();
            $models = $this->buildModelCards(
                $resolved->priceBook,
                $resolved->enabledRules,
                $resolved->supportedConfigurations,
                $labels,
                $catalog
            );
            $state = $this->requestState($resolved->supportedConfigurations, $catalog);
            $submissionError = sanitize_text_field((string) ($_GET['ak_buyback_submission_error'] ?? ''));
        } catch (\Throwable $exception) {
            return '<div class="ak-buyback-demo"><div class="ak-buyback-demo__notice"><strong>FELVÁSÁRLÁSI KALKULÁTOR</strong><p>'
                . esc_html($this->publicAvailabilityMessage($exception))
                . '</p></div></div>';
        }

        $selectedModel = (string) $state['model_key'];
        $initialLabel = $labels[$selectedModel] ?? '';
        $initialPanel = $state['show_results'] ? 'offers' : $state['panel'];
        $flow = array_merge(['entry'], $this->questionnaire->panelOrder());
        $visualCatalogue = new VisualStateCatalogue($this->questionnaire);
        $visualPayload = $this->visualPayload($visualCatalogue);

        ob_start();
        ?>
        <section
            class="ak-buyback-demo"
            data-initial-panel="<?php echo esc_attr($initialPanel); ?>"
            data-panel-order="<?php echo esc_attr((string) wp_json_encode($flow)); ?>"
            data-visual-catalogue="<?php echo esc_attr((string) wp_json_encode($visualPayload, JSON_UNESCAPED_SLASHES)); ?>"
        >
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
            <?php if ($submissionError !== '') : ?><div class="ak-buyback-demo__errors" role="alert"><strong>Nem sikerült elküldeni az igényt.</strong><p><?php echo esc_html($submissionError); ?></p></div><?php endif; ?>

            <form class="ak-buyback-demo__form" method="post" novalidate data-demo-form>
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="ak_demo_action" value="calculate">

                <?php $this->renderEntryPanel($models); ?>
                <?php $this->renderModelPanel($models, $state); ?>
                <?php $this->renderConfigurationPanel($models, $state, $visualPayload['fallback']); ?>
                <?php foreach ($this->questionnaire->panelOrder() as $panelKey) : ?>
                    <?php if (in_array($panelKey, ['model', 'configuration', 'offers', 'review'], true)) { continue; } ?>
                    <?php $this->renderQuestionnairePanel($panelKey, $state['answers'], $visualPayload['fallback']); ?>
                <?php endforeach; ?>
            </form>

            <div class="ak-buyback-demo__eligibility-modal" data-service-history-modal hidden role="dialog" aria-modal="true" aria-labelledby="ak-demo-service-history-title">
                <div class="ak-buyback-demo__eligibility-dialog">
                    <button type="button" class="ak-buyback-demo__modal-close" data-service-history-close aria-label="Bezárás">×</button>
                    <h4 id="ak-demo-service-history-title">Alkatrész- és szervizelési előzmények</h4>
                    <ol>
                        <li>Nyisd meg a Beállítások alkalmazást.</li>
                        <li>Válaszd az Általános menüpontot.</li>
                        <li>Nyisd meg az Infó részt.</li>
                        <li>Görgess az Alkatrész- és szervizelési előzményekhez.</li>
                        <li>Nézd meg, milyen jelölés szerepel az egyes alkatrészek mellett.</li>
                    </ol>
                    <p>Ha ez a szakasz nem jelenik meg, válaszd a „Nincs ilyen bejegyzés” opciót. Az elérhető információk modellenként és iOS-verziónként eltérhetnek.</p>
                    <button type="button" class="ak-buyback-demo__primary" data-service-history-close>Bezárás</button>
                </div>
            </div>

            <?php if ($state['show_results']) : ?>
                <?php $this->renderResults($resolved->priceBook, $resolved->enabledRules, $state, $labels, $models); ?>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function publicAvailabilityMessage(\Throwable $exception): string
    {
        return match (true) {
            str_contains($exception->getMessage(), 'No active') || str_contains($exception->getMessage(), 'No current active') => 'Jelenleg nincs aktív felvásárlási árkönyv.',
            str_contains($exception->getMessage(), 'Multiple active') => 'A felvásárlási kalkulátor átmenetileg nem érhető el az árkönyv-beállítás hibája miatt.',
            default => 'Az aktív felvásárlási árkönyv nem tölthető be.',
        };
    }

    /** @return array{url:string,marker:string,legal_basis:string} */
    private function privacyNotice(): array
    {
        $url = get_privacy_policy_url();
        if ($url === '') {
            $url = home_url('/privacy-policy/');
        }
        $legalBasis = (string) apply_filters(
            'appleklinika_buyback_privacy_legal_basis',
            'jogos érdek a felvásárlási igény feldolgozásához és a kapcsolatfelvételhez'
        );
        return ['url' => $url, 'marker' => hash('sha256', $url), 'legal_basis' => $legalBasis];
    }

    /** @param array<string,mixed> $success */
    private function renderSuccess(array $success): string
    {
        $manualReview = ($success['manual_review'] ?? false) === true;
        $mode = OfferModeDefinition::all()[(string) ($success['service_mode'] ?? '')]['label'] ?? 'Kiválasztott ajánlat';
        $amount = isset($success['amount_minor']) && is_numeric($success['amount_minor']) ? $this->money((int) $success['amount_minor']) : null;
        ob_start();
        ?>
        <section class="ak-buyback-demo ak-buyback-demo--success">
            <header class="ak-buyback-demo__header"><span class="ak-buyback-demo__badge">FELVÁSÁRLÁSI IGÉNY</span><h2>Felvásárlási igényedet megkaptuk</h2><p>Hamarosan felvesszük veled a kapcsolatot a megadott elérhetőségeken.</p></header>
            <article class="ak-buyback-demo__contact-card">
                <h3>Hivatkozási szám: <?php echo esc_html((string) ($success['request_number'] ?? '')); ?></h3>
                <p><strong>Készülék:</strong> <?php echo esc_html((string) ($success['device'] ?? '')); ?></p>
                <?php if ($manualReview) : ?>
                    <p><strong>Következő lépés:</strong> Személyes bevizsgálás után küldünk pontos ajánlatot.</p>
                    <?php if (($success['manual_review_reasons'] ?? []) !== []) : ?><p><strong>Rögzített okok:</strong> <?php echo esc_html(implode(' · ', (array) $success['manual_review_reasons'])); ?></p><?php endif; ?>
                <?php else : ?>
                    <p><strong>Választott lehetőség:</strong> <?php echo esc_html($mode); ?></p>
                    <?php if ($amount !== null) : ?><p><strong>Előzetes ajánlat:</strong> <?php echo esc_html($amount); ?></p><?php endif; ?>
                <?php endif; ?>
                <div class="ak-buyback-demo__demo-notice"><strong>FONTOS</strong><span>Az összeg előzetes tájékoztatás; a végleges értéket a készülék fizikai bevizsgálása után tudjuk megerősíteni.</span></div>
                <p>A visszaigazolást a megadott e-mail-címre is elküldtük, ha a helyi levelezési beállítás ezt lehetővé teszi.</p>
                <a class="ak-buyback-demo__secondary ak-buyback-demo__primary--link" href="<?php echo esc_url(get_permalink()); ?>">Új felvásárlási igény indítása</a>
            </article>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param list<\AppleKlinika\Buyback\Domain\Pricing\SupportedPriceConfiguration> $configurations
     * @param array<string,array{label:string,colors:array<string,string>}> $catalog
     * @return array{panel:string,model_key:string,storage_gb:int,color_key:string,answers:array<string,mixed>,errors:list<string>,show_results:bool}
     */
    private function requestState(array $configurations, array $catalog): array
    {
        $state = [
            'panel' => 'entry',
            'model_key' => '',
            'storage_gb' => 0,
            'color_key' => '',
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
        $state['color_key'] = sanitize_key((string) wp_unslash($_POST['color_key'] ?? ''));
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

        if (! $this->colorExists($catalog, $state['model_key'], $state['color_key'])) {
            $state['errors'][] = 'Válassz az ehhez a modellhez elérhető színek közül.';
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

    /** @param array<string,array{label:string,colors:array<string,string>}> $catalog */
    private function colorExists(array $catalog, string $modelKey, string $colorKey): bool
    {
        return $colorKey !== '' && isset($catalog[$modelKey]['colors'][$colorKey]);
    }

    /** @param array<string,array{label:string,image_url:string,storages:list<int>,teaser:?int}> $models */
    private function renderEntryPanel(array $models): void
    {
        $iphone = $models['iphone_13_pro'] ?? $models['iphone_11'] ?? reset($models);
        $imageUrl = is_array($iphone) ? (string) ($iphone['image_url'] ?? '') : '';
        ?>
        <section class="ak-buyback-demo__entry" data-demo-panel="entry" data-step-title="Készüléktípus">
            <div class="ak-buyback-demo__entry-intro">
                <span class="ak-buyback-demo__eyebrow">APPLE KLINIKA BUYBACK</span>
                <h2>Milyen Apple készüléket adnál el?</h2>
                <p>Válaszd ki a készülék típusát, és néhány lépésben megmutatjuk az előzetes felvásárlási ajánlatot.</p>
            </div>
            <div class="ak-buyback-demo__entry-family-grid">
                <button class="ak-buyback-demo__entry-family" type="button" data-entry-family="iphone" aria-pressed="false" data-demo-next data-demo-target="model">
                    <span class="ak-buyback-demo__entry-family-media"><?php $this->renderImage($imageUrl, 'iPhone felvásárlás', 'ak-buyback-demo__entry-family-image'); ?></span>
                    <span class="ak-buyback-demo__entry-family-copy"><strong>iPhone</strong><span>iPhone kiválasztása</span></span>
                    <span class="ak-buyback-demo__entry-family-arrow" aria-hidden="true">→</span>
                </button>
            </div>
            <p class="ak-buyback-demo__entry-coming">További Apple készüléktípusok támogatása később érkezik.</p>
            <div class="ak-buyback-demo__entry-benefits" aria-label="A felvásárlás előnyei">
                <article><h3>Gyors előzetes ajánlat</h3><p>Néhány kérdés alapján azonnal láthatod a lehetséges összegeket.</p></article>
                <article><h3>Átlátható állapotfelmérés</h3><p>Lépésről lépésre megadhatod a készülék állapotát.</p></article>
                <article><h3>Személyes bevizsgálás</h3><p>A végleges értéket az Apple Klinika szakembere a készülék ellenőrzése után erősíti meg.</p></article>
            </div>
        </section>
        <?php
    }

    /** @param array<string,array{label:string,image_url:string,storages:list<int>,teaser:?int}> $models @param array<string,mixed> $state */
    private function renderModelPanel(array $models, array $state): void
    {
        ?>
        <section class="ak-buyback-demo__panel" data-demo-panel="model" data-step-title="Modell kiválasztása" hidden>
            <div class="ak-buyback-demo__model-content" data-model-content>
                <div class="ak-buyback-demo__panel-heading"><span class="ak-buyback-demo__eyebrow">1. Készülék</span><h3>Válaszd ki az iPhone modelled</h3><p>Keress a modell nevére, vagy válassz a kártyák közül.</p></div>
                <label class="ak-buyback-demo__search"><span class="screen-reader-text">Modell keresése</span><input type="search" placeholder="Keresés az iPhone modellek között" data-model-search></label>
                <div class="ak-buyback-demo__device-grid" data-model-grid>
                    <?php foreach ($models as $key => $model) : $id = 'ak-demo-model-' . sanitize_html_class($key); ?>
                        <label class="ak-buyback-demo__device-card" data-model-card data-model-key="<?php echo esc_attr($key); ?>" data-search-text="<?php echo esc_attr(strtolower($model['label'])); ?>" data-image="<?php echo esc_url($model['image_url']); ?>" data-label="<?php echo esc_attr($model['label']); ?>" data-storages="<?php echo esc_attr(implode(',', $model['storages'])); ?>" data-colors="<?php echo esc_attr((string) wp_json_encode($model['colors'])); ?>">
                            <input type="radio" id="<?php echo esc_attr($id); ?>" name="model_key" value="<?php echo esc_attr($key); ?>" <?php checked($state['model_key'], $key); ?> required>
                            <span class="ak-buyback-demo__device-media" data-model-media><?php $this->renderImage($model['image_url'], $model['label'], 'ak-buyback-demo__model-media-image'); ?></span>
                            <span class="ak-buyback-demo__device-info"><strong><?php echo esc_html($model['label']); ?></strong><?php if ($model['teaser'] !== null) : ?><small>Akár <?php echo esc_html($this->money($model['teaser'])); ?></small><?php endif; ?></span>
                            <span class="ak-buyback-demo__selected-mark">Kiválasztva</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="ak-buyback-demo__model-no-results" data-model-no-results hidden>Nincs a keresésnek megfelelő iPhone-modell.</p>
            </div>
            <?php $this->renderPanelActions('entry', 'configuration', 'Tovább a tárhelyhez'); ?>
        </section>
        <?php
    }

    /** @param array<string,array{label:string,image_url:string,storages:list<int>,teaser:?int}> $models @param array<string,mixed> $state */
    private function renderConfigurationPanel(array $models, array $state, array $fallbackVisual): void
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
            <div class="ak-buyback-demo__split ak-buyback-demo__wizard-shell" data-demo-wizard-shell>
                <?php $this->renderVisual($fallbackVisual); ?>
                <div class="ak-buyback-demo__question ak-buyback-demo__question--configuration">
                    <div class="ak-buyback-demo__configuration-content" data-configuration-content>
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
                        <div class="ak-buyback-demo__color-picker" data-color-picker data-current-color="<?php echo esc_attr($state['color_key']); ?>" hidden>
                            <h4>Szín</h4><p class="ak-buyback-demo__question-helper">Csak az ehhez a modellhez és tárhelyhez elérhető színek jelennek meg. A szín nem módosítja az ajánlatot.</p>
                            <div class="ak-buyback-demo__choice-grid ak-buyback-demo__choice-grid--two" data-color-options></div>
                        </div>
                        <div class="ak-buyback-demo__configuration-divider"></div>
                        <?php $networkQuestion = $this->questionnaire->questions()['network_status']; ?>
                        <?php $this->renderQuestion('network_status', $networkQuestion, $state['answers']['network_status'] ?? $networkQuestion['default']); ?>
                    </div>
                    <?php $this->renderPanelActions('model', 'liquid_contact', 'Tovább az állapotfelméréshez'); ?>
                </div>
            </div>
            <div class="ak-buyback-demo__eligibility-modal" data-network-modal hidden role="dialog" aria-modal="true" aria-labelledby="ak-demo-network-title">
                <div class="ak-buyback-demo__eligibility-dialog">
                    <button type="button" class="ak-buyback-demo__modal-close" data-network-close aria-label="Bezárás">×</button>
                    <span class="ak-buyback-demo__modal-icon" aria-hidden="true">!</span>
                    <h4 id="ak-demo-network-title">Hálózatfüggő készüléket jelenleg nem vásárolunk fel</h4>
                    <p>A felvásárláshoz a készüléknek minden magyarországi és külföldi mobilhálózaton használhatónak kell lennie. Ellenőrizd a hálózati állapotot, majd térj vissza és módosítsd a választ.</p>
                    <button type="button" class="ak-buyback-demo__primary" data-network-close>Vissza a választáshoz</button>
                </div>
            </div>
        </section>
        <?php
    }

    /** @param array<string,mixed> $answers */
    private function renderQuestionnairePanel(string $panelKey, array $answers, array $fallbackVisual): void
    {
        $panel = $this->questionnaire->panel($panelKey);
        $questions = $this->questionnaire->questionsForPanel($panelKey);
        $order = $this->questionnaire->panelOrder();
        $index = array_search($panelKey, $order, true);
        $previous = is_int($index) && $index > 0 ? $order[$index - 1] : 'entry';
        $next = is_int($index) && isset($order[$index + 1]) ? $order[$index + 1] : 'offers';
        ?>
        <section class="ak-buyback-demo__panel" data-demo-panel="<?php echo esc_attr($panelKey); ?>" data-step-title="<?php echo esc_attr($panel['short']); ?>" hidden>
            <div class="ak-buyback-demo__split ak-buyback-demo__wizard-shell" data-demo-wizard-shell>
                <?php $this->renderVisual($fallbackVisual); ?>
                <div class="ak-buyback-demo__question ak-buyback-demo__question--questionnaire">
                    <span class="ak-buyback-demo__eyebrow"><?php echo esc_html($panel['step'] . '. ' . $panel['short']); ?></span>
                    <h3><?php echo esc_html($panel['title']); ?></h3>
                    <p><?php echo esc_html($this->panelIntro($panelKey)); ?></p>
                    <div class="ak-buyback-demo__question-stack">
                        <?php if ($panelKey === 'service_history') : ?>
                            <button type="button" class="ak-buyback-demo__secondary" data-service-history-open>Hol tudom ezt megnézni?</button>
                        <?php endif; ?>
                        <?php foreach ($questions as $key => $question) : ?>
                            <?php $this->renderQuestion((string) $key, $question, $answers[$key] ?? $question['default'], true); ?>
                        <?php endforeach; ?>
                    </div>
                    <?php $this->renderPanelActions($previous, $next, $next === 'offers' ? 'Előzetes ajánlatok' : 'Tovább', $next === 'offers'); ?>
                </div>
            </div>
        </section>
        <?php
    }

    /** @param array<string,mixed> $question */
    private function renderQuestion(string $key, array $question, mixed $value, bool $hideDuplicateLabel = false): void
    {
        if ($question['type'] === 'range') {
            $this->renderRangeQuestion($key, $question, (int) $value);
            return;
        }

        $type = (string) $question['type'];
        $isMulti = $type === 'multi';
        $hideDuplicateLabel = $hideDuplicateLabel && ! $isMulti;
        $selectedValues = $isMulti ? array_map('strval', (array) $value) : [(string) $value];
        $gridClass = count($question['options']) <= 3 ? ' ak-buyback-demo__choice-grid--two' : '';
        if ($key === 'liquid_exposure') {
            $gridClass .= ' ak-buyback-demo__choice-grid--liquid-contact';
        }
        ?>
        <fieldset
            class="ak-buyback-demo__question-group"
            data-question
            data-question-key="<?php echo esc_attr($key); ?>"
            data-question-type="<?php echo esc_attr($type); ?>"
            <?php if ($isMulti && isset($question['exclusive'])) : ?>data-exclusive-value="<?php echo esc_attr((string) $question['exclusive']); ?>"<?php endif; ?>
            <?php if (isset($question['conditional_on'])) : ?>data-conditional-on="<?php echo esc_attr((string) $question['conditional_on']); ?>" data-conditional-except="<?php echo esc_attr((string) ($question['conditional_except'] ?? '')); ?>" hidden<?php endif; ?>
        >
            <legend<?php echo $hideDuplicateLabel ? ' class="screen-reader-text"' : ''; ?>><?php echo esc_html((string) $question['label']); ?></legend>
            <?php if (! empty($question['helper'])) : ?><p class="ak-buyback-demo__question-helper"><?php echo esc_html((string) $question['helper']); ?></p><?php endif; ?>
            <div class="ak-buyback-demo__choice-grid<?php echo esc_attr($gridClass); ?>">
                <?php foreach ($question['options'] as $option => $meta) :
                    $id = 'ak-demo-' . sanitize_html_class($key . '-' . $option);
                    $checked = in_array((string) $option, $selectedValues, true);
                    ?>
                    <label class="ak-buyback-demo__choice-card" data-choice-key="<?php echo esc_attr($key); ?>" data-choice-value="<?php echo esc_attr((string) $option); ?>" data-visual-key="<?php echo esc_attr((string) ($meta['visual_key'] ?? '')); ?>">
                        <input
                            id="<?php echo esc_attr($id); ?>"
                            type="<?php echo $isMulti ? 'checkbox' : 'radio'; ?>"
                            name="questionnaire[<?php echo esc_attr($key); ?>]<?php echo $isMulti ? '[]' : ''; ?>"
                            value="<?php echo esc_attr((string) $option); ?>"
                            <?php checked($checked); ?>
                            <?php echo $isMulti ? '' : 'required'; ?>
                            <?php if (! $isMulti && ! empty($meta['helper'])) : ?>aria-expanded="<?php echo $checked ? 'true' : 'false'; ?>"<?php endif; ?>
                            <?php if (! $isMulti && ! empty($meta['helper']) && $checked) : ?>aria-describedby="<?php echo esc_attr($id . '-description'); ?>"<?php endif; ?>
                        >
                        <strong><?php echo esc_html((string) $meta['label']); ?></strong>
                        <?php if (! empty($meta['helper'])) : ?><span class="ak-buyback-demo__choice-description" id="<?php echo esc_attr($id . '-description'); ?>"<?php echo ! $isMulti && ! $checked ? ' hidden' : ''; ?>><?php echo esc_html((string) $meta['helper']); ?></span><?php endif; ?>
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
        $questionnaireManualReasons = $this->questionnaire->manualReviewReasons($questionnaireAnswers);
        $collection = ConditionAnswerCollection::fromAssociative($canonicalAnswers);
        $results = [];
        $highest = 0;

        foreach (OfferModeDefinition::keys() as $mode) {
            $serviceMode = new ServiceMode($mode);
            $calculated = $this->engine->calculate(
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
            $result = $this->manualResultIfRequired($book, $serviceMode, $questionnaireManualReasons, $calculated);
            $results[$mode] = $result;
            if ($result->outcome->code() === PricingOutcome::OFFERED) {
                $highest = max($highest, $result->finalAmount?->amount() ?? 0);
            }
        }

        $modelLabel = $labels[$state['model_key']] ?? $state['model_key'];
        $image = $models[$state['model_key']]['image_url'] ?? '';
        $storageLabel = $this->storageLabel($state['storage_gb']);
        $colorLabel = (string) ($models[$state['model_key']]['colors'][(int) $state['storage_gb']][$state['color_key']] ?? '');
        $summary = $this->questionnaire->summary($questionnaireAnswers, $modelLabel, $storageLabel, $colorLabel);
        $representative = $results[ServiceMode::FAST_ONLINE] ?? reset($results);
        $resultState = $representative->outcome->code();
        $isManualReview = $resultState === PricingOutcome::MANUAL_REVIEW;
        $isCalculated = $resultState === PricingOutcome::OFFERED;
        $manualReasons = $isManualReview ? $this->publicManualReasons($representative, $questionnaireAnswers) : [];
        $canSubmit = $isCalculated || $isManualReview;
        ?>
        <section class="ak-buyback-demo__panel ak-buyback-demo__panel--offers" data-demo-panel="offers" data-step-title="Ajánlat" hidden>
            <div class="ak-buyback-demo__panel-heading">
                <span class="ak-buyback-demo__eyebrow">10. Ajánlat</span>
                <h3><?php echo esc_html($isManualReview ? 'Személyes bevizsgálás szükséges' : ($isCalculated ? 'Válaszd ki a számodra megfelelő lehetőséget' : $this->resultHeadline($representative))); ?></h3>
                <p><?php echo esc_html($modelLabel . ' · ' . $storageLabel); ?></p>
            </div>
            <?php if ($isCalculated) : ?><div class="ak-buyback-demo__demo-notice">
                <strong>ELŐZETES AJÁNLAT</strong>
                <span>A feltüntetett összeg a megadott információk alapján készült. A végleges ajánlatot a készülék személyes bevizsgálása után tudjuk megerősíteni.</span>
            </div>
            <fieldset class="ak-buyback-demo__mode-fieldset" data-offer-group>
                <legend class="screen-reader-text">Ajánlattípus kiválasztása</legend>
                <div class="ak-buyback-demo__mode-grid">
                    <?php foreach (OfferModeDefinition::all() as $mode => $meta) :
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
                            data-mode-title="<?php echo esc_attr($meta['label']); ?>"
                            data-mode-headline="<?php echo esc_attr($headline); ?>"
                            data-mode-description="<?php echo esc_attr($description); ?>"
                            data-mode-process="<?php echo esc_attr($meta['process']); ?>"
                        >
                            <input type="radio" id="<?php echo esc_attr($id); ?>" name="selected_offer_mode" value="<?php echo esc_attr($mode); ?>" data-mode-input>
                            <div class="ak-buyback-demo__mode-badges">
                                <?php if ($isHighest) : ?><span>Legmagasabb összeg</span><?php endif; ?>
                                <?php if ($mode === ServiceMode::FAST_ONLINE) : ?><span>Kényelmes választás</span><?php endif; ?>
                            </div>
                            <h4><?php echo esc_html($meta['label']); ?></h4>
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
            <?php elseif ($isManualReview) : ?>
                <article class="ak-buyback-demo__contact-card ak-buyback-demo__manual-review" data-manual-review-result>
                    <h4>Személyes bevizsgálás szükséges</h4>
                    <p>A megadott állapot alapján most nem tudunk megbízható online árat adni. A készüléket rövid személyes ellenőrzés után tudjuk pontosan értékelni.</p>
                    <h5>Miért szükséges az ellenőrzés?</h5>
                    <ul><?php foreach ($manualReasons as $reason) : ?><li><?php echo esc_html($reason); ?></li><?php endforeach; ?></ul>
                    <button class="ak-buyback-demo__primary" type="button" data-demo-next data-demo-target="review" data-manual-review-route>Személyes bevizsgálást kérek</button>
                </article>
            <?php else : ?>
                <div class="ak-buyback-demo__demo-notice" data-non-offer-result><strong><?php echo esc_html($this->resultHeadline($representative)); ?></strong><span><?php echo esc_html($this->safeReason($representative)); ?></span></div>
            <?php endif; ?>
            <?php $this->renderCustomerSummary($summary, $isManualReview); ?>
            <?php if ($isCalculated) : ?><p class="ak-buyback-demo__mode-message" data-mode-message hidden>A kiválasztott ajánlat a beküldés előtt még egyszer szerveroldalon ellenőrzésre kerül.</p><?php endif; ?>
            <div class="ak-buyback-demo__panel-actions">
                <button class="ak-buyback-demo__secondary" type="button" data-demo-back data-demo-target="other_defects"><?php echo esc_html($isManualReview ? 'Vissza és módosítom a válaszaimat' : 'Vissza'); ?></button>
                <?php if ($isCalculated) : ?><button class="ak-buyback-demo__primary" type="button" data-demo-next data-demo-target="review" data-offer-continue disabled>Tovább az adatok megadásához</button><?php endif; ?>
            </div>
        </section>

        <section class="ak-buyback-demo__panel ak-buyback-demo__panel--review" data-demo-panel="review" data-step-title="Összegzés" hidden>
            <div class="ak-buyback-demo__panel-heading">
                <span class="ak-buyback-demo__eyebrow">11. Összegzés</span>
                <h3>Ellenőrizd a megadott adatokat</h3>
                <p>Módosításhoz bármikor visszaléphetsz. Beküldés előtt ellenőrizd az adataidat.</p>
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
                    <?php if ($isManualReview) : ?>
                    <h4>Személyes bevizsgálás</h4>
                    <strong>Személyes bevizsgálást kérek</strong>
                    <p>A pontos ajánlatot a rövid személyes ellenőrzés után adjuk meg.</p>
                    <?php else : ?>
                    <h4>Kiválasztott lehetőség</h4>
                    <strong data-review-mode-title>Nincs kiválasztva</strong>
                    <span class="ak-buyback-demo__review-amount" data-review-mode-headline>—</span>
                    <p data-review-mode-description>Válassz egy ajánlattípust az előző képernyőn.</p>
                    <small data-review-mode-process></small>
                    <?php endif; ?>
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
            <?php if ($canSubmit) : ?>
                <?php $this->renderSubmissionForm($book, $state, $questionnaireAnswers, $modelLabel, $storageLabel, $colorLabel, $isManualReview); ?>
            <?php else : ?>
                <div class="ak-buyback-demo__demo-notice"><strong>EGYEDI EGYEZTETÉS SZÜKSÉGES</strong><span>Ehhez az állapothoz jelenleg nem adható beküldhető automatikus ajánlat. Kérjük, vedd fel velünk a kapcsolatot személyes bevizsgáláshoz.</span></div>
            <?php endif; ?>
            <div class="ak-buyback-demo__panel-actions">
                <button class="ak-buyback-demo__secondary" type="button" data-demo-back data-demo-target="offers"><?php echo esc_html($isManualReview ? 'Vissza és módosítom a válaszaimat' : 'Vissza és módosítás'); ?></button>
                <a class="ak-buyback-demo__secondary ak-buyback-demo__primary--link" href="<?php echo esc_url(get_permalink()); ?>">Elölről kezdem</a>
            </div>
        </section>
        <?php
    }

    /** @param \AppleKlinika\Buyback\Domain\Pricing\PriceBook $book @param array<string,mixed> $state @param array<string,mixed> $answers */
    private function renderSubmissionForm($book, array $state, array $answers, string $modelLabel, string $storageLabel, string $colorLabel, bool $manualReview = false): void
    {
        $privacy = $this->privacyNotice();
        ?>
        <form class="ak-buyback-demo__submission-form" method="post" data-public-request-form>
            <?php wp_nonce_field(self::SUBMISSION_NONCE_ACTION, self::SUBMISSION_NONCE_NAME); ?>
            <input type="hidden" name="ak_buyback_action" value="submit_request">
            <input type="hidden" name="idempotency_token" value="<?php echo esc_attr(bin2hex(random_bytes(32))); ?>">
            <input type="hidden" name="form_started_at" value="<?php echo esc_attr((string) time()); ?>">
            <input type="hidden" name="price_book_id" value="<?php echo esc_attr((string) $book->id()?->toInt()); ?>">
            <input type="hidden" name="price_book_version" value="<?php echo esc_attr((string) $book->versionNumber()->value()); ?>">
            <input type="hidden" name="model_key" value="<?php echo esc_attr((string) $state['model_key']); ?>">
            <input type="hidden" name="storage_gb" value="<?php echo esc_attr((string) $state['storage_gb']); ?>">
            <input type="hidden" name="color_key" value="<?php echo esc_attr((string) $state['color_key']); ?>">
            <input type="hidden" name="selected_offer_mode" value="" data-selected-offer-mode>
            <?php if ($manualReview) : ?><input type="hidden" name="manual_review_requested" value="1"><?php endif; ?>
            <input class="ak-buyback-demo__honeypot" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
            <?php foreach ($answers as $key => $value) : ?>
                <?php if (is_array($value)) : foreach ($value as $item) : ?><input type="hidden" name="questionnaire[<?php echo esc_attr((string) $key); ?>][]" value="<?php echo esc_attr((string) $item); ?>"><?php endforeach; ?>
                <?php else : ?><input type="hidden" name="questionnaire[<?php echo esc_attr((string) $key); ?>]" value="<?php echo esc_attr((string) $value); ?>"><?php endif; ?>
            <?php endforeach; ?>
            <div class="ak-buyback-demo__contact-card">
                <span class="ak-buyback-demo__eyebrow">12. Kapcsolattartás</span>
                <h4>Hová küldhetjük a következő lépéseket?</h4>
                <p>Csak a felvásárlási igény feldolgozásához és a kapcsolatfelvételhez szükséges adatokat kérjük.</p>
                <div class="ak-buyback-demo__contact-grid">
                    <label>Teljes név<input name="full_name" type="text" autocomplete="name" maxlength="191" required></label>
                    <label>E-mail-cím<input name="email" type="email" autocomplete="email" maxlength="191" required></label>
                    <label>Telefonszám<input name="phone" type="tel" autocomplete="tel" maxlength="64" required></label>
                    <label class="ak-buyback-demo__contact-grid--wide">Megjegyzés (opcionális)<textarea name="customer_note" rows="3" maxlength="1000"></textarea></label>
                </div>
                <div class="ak-buyback-demo__privacy-notice">
                    <h5>Adatkezelési tájékoztató</h5>
                    <p>Az Apple Klinika a nevedet, e-mail-címedet, telefonszámodat, megjegyzésedet és a készülék megadott adatait a felvásárlási igény kezeléséhez és a kapcsolatfelvételhez kezeli. Az előzetes összeg automatizált számítás, nem végleges elfogadás; a végleges érték fizikai bevizsgálástól függ. Jogi alap: <?php echo esc_html($privacy['legal_basis']); ?>. A megőrzésről, jogaidról, panaszlehetőségről és esetleges adatfeldolgozókról a <a href="<?php echo esc_url($privacy['url']); ?>" target="_blank" rel="noopener">teljes adatkezelési tájékoztatóban</a> olvashatsz.</p>
                    <label class="ak-buyback-demo__privacy-check"><input type="checkbox" name="privacy_acknowledged" value="1" required> Elolvastam és tudomásul vettem az adatkezelési tájékoztatót.</label>
                </div>
                <p class="ak-buyback-demo__submission-device"><strong>Készülék:</strong> <?php echo esc_html($modelLabel . ' · ' . $storageLabel . ' · ' . $colorLabel); ?></p>
                <button class="ak-buyback-demo__primary" type="submit" data-public-submit<?php echo $manualReview ? '' : ' disabled'; ?>>Felvásárlási igény elküldése</button>
                <?php if (! $manualReview) : ?><p class="ak-buyback-demo__mode-message" data-public-submit-message>Előbb válassz ajánlattípust az előző lépésben.</p><?php endif; ?>
            </div>
        </form>
        <?php
    }

    /** @param \AppleKlinika\Buyback\Domain\Pricing\PriceBook $book @param list<\AppleKlinika\Buyback\Domain\Pricing\PricingRule> $rules @param list<\AppleKlinika\Buyback\Domain\Pricing\SupportedPriceConfiguration> $configurations @param array<string,string> $labels @param array<string,array{label:string,colors:array<string,string>}> $catalog @return array<string,array{label:string,image_url:string,storages:list<int>,colors:array<int,array<string,string>>,teaser:?int}> */
    private function buildModelCards($book, array $rules, array $configurations, array $labels, array $catalog): array
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
                    'colors' => [],
                    'teaser' => null,
                ];
            }
            $models[$key]['storages'][$configuration->storageGb] = $configuration->storageGb;
            $models[$key]['colors'][$configuration->storageGb] = $catalog[$key]['colors'] ?? [];
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
            'service_history' => 'Az iPhone Beállítások / Általános / Infó részében ellenőrizheted a megjelenő bejegyzéseket.',
            'other_defects' => 'Jelöld meg az összes olyan funkciót, amely nem működik megfelelően.',
            default => 'Válaszd ki a készülékedre leginkább jellemző választ.',
        };
    }

    /** @param array{visual_key:string,question_key:string,answer_key:string,question_label:string,answer_label:string,panel:string,view_type:string,expected_path:string,fallback_path:string,alt:string,active_url:string,asset_exists:bool} $visual */
    private function renderVisual(array $visual): void
    {
        echo '<aside class="ak-buyback-demo__visual" data-demo-visual data-visual-key="' . esc_attr($visual['visual_key']) . '" data-visual-view="' . esc_attr($visual['view_type']) . '" aria-label="A készülék állapotának illusztrációja">';
        echo '<div class="ak-buyback-demo__visual-image"><img src="' . esc_url($visual['active_url']) . '" alt="' . esc_attr($visual['alt']) . '" loading="eager" data-demo-device-image></div>';
        echo '<strong data-demo-visual-label>' . esc_html($visual['answer_label']) . '</strong><span data-demo-visual-description>A készülék állapotának szemléltetése</span></aside>';
    }

    /** @return array{assets:array<string,array<string,mixed>>,fallback:array<string,mixed>} */
    private function visualPayload(VisualStateCatalogue $catalogue): array
    {
        $resolve = static function (array $entry): array {
            $expected = APPLEKLINIKA_BUYBACK_PATH . '/' . $entry['expected_path'];
            $exists = is_file($expected);
            $activePath = $exists ? $entry['expected_path'] : $entry['fallback_path'];
            $entry['asset_exists'] = $exists;
            $entry['active_url'] = APPLEKLINIKA_BUYBACK_URL . $activePath;
            $entry['fallback_url'] = APPLEKLINIKA_BUYBACK_URL . $entry['fallback_path'];

            return $entry;
        };

        return [
            'assets' => array_map($resolve, $catalogue->entries()),
            'fallback' => $resolve($catalogue->fallback()),
        ];
    }

    private function renderImage(string $url, string $alt, string $class = ''): void
    {
        if ($url !== '') {
            echo '<img' . ($class !== '' ? ' class="' . esc_attr($class) . '"' : '') . ' src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy">';
            return;
        }

        echo '<span class="ak-buyback-demo__image-fallback' . ($class !== '' ? ' ' . esc_attr($class) : '') . '"><span></span>iPhone</span>';
    }

    private function renderPanelActions(string $back, string $next, string $nextLabel, bool $submit = false): void
    {
        echo '<div class="ak-buyback-demo__panel-actions" data-wizard-action-bar>';
        echo '<button class="ak-buyback-demo__secondary" type="button" data-demo-back data-demo-target="' . esc_attr($back) . '">Vissza</button>';
        if ($submit) {
            echo '<button class="ak-buyback-demo__primary" type="submit">' . esc_html($nextLabel) . '</button>';
        } else {
            echo '<button class="ak-buyback-demo__primary" type="button" data-demo-next data-demo-target="' . esc_attr($next) . '">' . esc_html($nextLabel) . '</button>';
        }
        echo '</div>';
    }

    /** @param array<string,array<string,string>> $summary */
    private function renderCustomerSummary(array $summary, bool $manualReview = false): void
    {
        $configuration = $summary['Konfiguráció'] ?? [];
        $condition = $summary['Állapot'] ?? [];
        $service = $summary['Alkatrész- és szervizelési előzmények'] ?? [];
        $deviceLine = implode(' · ', array_filter([
            $summary['Készülék']['Modell'] ?? '',
            $configuration['Tárhely'] ?? '',
            $configuration['Szín'] ?? '',
        ]));
        $conditionRows = [
            'Folyadékérintkezés' => $condition['Folyadék / pára'] ?? '',
            'Kijelző állapota' => $condition['Kijelző'] ?? '',
            'Kijelző működése' => $summary['Kijelzőhibák']['Megjelölt válaszok'] ?? '',
            'Keret' => $condition['Keret'] ?? '',
            'Hátlap' => $condition['Hátlap'] ?? '',
            'Akkumulátor' => $summary['Akkumulátor']['Állapot'] ?? '',
        ];
        $serviceRows = [
            'Szervizelőzmény' => $service['Állapot'] ?? '',
            'Érintett alkatrészek' => $service['Érintett alkatrészek'] ?? '',
            'Egyéb hibák' => $summary['Egyéb hibák']['Megjelölt válaszok'] ?? '',
        ];
        ?>
        <section class="ak-buyback-demo__customer-summary" data-customer-summary aria-labelledby="ak-demo-customer-summary-title">
            <div class="ak-buyback-demo__customer-summary-heading">
                <h4 id="ak-demo-customer-summary-title">A készüléked összefoglalója</h4>
                <p>Ellenőrizd, hogy minden megadott adat helyes-e.</p>
            </div>
            <div class="ak-buyback-demo__customer-summary-device"><strong><?php echo esc_html($deviceLine); ?></strong><span><?php echo esc_html((string) ($configuration['Hálózat'] ?? '')); ?></span></div>
            <?php $this->renderCustomerSummaryRows('Állapot', $conditionRows); ?>
            <?php $this->renderCustomerSummaryRows('Szervizelőzmény és egyéb hibák', $serviceRows); ?>
            <section class="ak-buyback-demo__customer-summary-offer">
                <h5><?php echo esc_html($manualReview ? 'Következő lépés' : 'Kiválasztott lehetőség'); ?></h5>
                <strong<?php echo $manualReview ? '' : ' data-offer-summary-selection'; ?>><?php echo esc_html($manualReview ? 'Személyes bevizsgálást kérek' : 'Még nincs kiválasztva.'); ?></strong>
            </section>
        </section>
        <?php
    }

    /** @param array<string,string> $rows */
    private function renderCustomerSummaryRows(string $heading, array $rows): void
    {
        ?>
        <section class="ak-buyback-demo__customer-summary-section">
            <h5><?php echo esc_html($heading); ?></h5>
            <dl><?php foreach ($rows as $label => $answer) : ?><?php if ($answer !== '') : ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($answer); ?></dd></div><?php endif; ?><?php endforeach; ?></dl>
        </section>
        <?php
    }

    /** @param list<string> $questionnaireReasons */
    private function manualResultIfRequired($book, ServiceMode $mode, array $questionnaireReasons, PricingCalculationResult $calculated): PricingCalculationResult
    {
        if ($calculated->outcome->code() !== PricingOutcome::MANUAL_REVIEW && $questionnaireReasons === []) {
            return $calculated;
        }

        if (in_array($calculated->outcome->code(), [PricingOutcome::REJECTED, PricingOutcome::CONFIGURATION_ERROR], true)) {
            return $calculated;
        }

        return PricingCalculationResult::manualReview(
            $book,
            $mode,
            array_merge($calculated->reasonCodes, $questionnaireReasons),
            $calculated->matchedRules,
            $calculated->breakdown,
            $calculated->calculatorVersion
        );
    }

    /** @return list<string> */
    private function publicManualReasons(PricingCalculationResult $result, array $answers): array
    {
        $reasons = [];
        foreach ($result->matchedRules as $rule) {
            $reasons[] = $this->questionnaire->publicManualReviewReason(
                null,
                $rule->publicLabel ?? '',
                $answers
            );
        }
        $matchedCodes = array_map(static fn ($rule): string => $rule->ruleCode, $result->matchedRules);
        foreach ($result->reasonCodes as $reason) {
            if ($reason === '' || in_array($reason, $matchedCodes, true)) {
                continue;
            }
            $reasons[] = $reason === 'below_minimum_offer'
                ? 'Az előzetes ajánlat pontosításához személyes bevizsgálás szükséges.'
                : $this->questionnaire->publicManualReviewReason(null, $reason, $answers);
        }

        return array_values(array_unique(array_filter($reasons, static fn (string $reason): bool => $reason !== '')));
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
