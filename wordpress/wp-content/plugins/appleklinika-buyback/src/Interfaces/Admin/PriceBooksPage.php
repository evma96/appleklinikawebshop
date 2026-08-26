<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Application\Command\AddDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\ActivateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\CreateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\ClonePriceBookToDraft;
use AppleKlinika\Buyback\Application\Command\DiscardDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\ProtectPriceBook;
use AppleKlinika\Buyback\Application\Command\SaveDraftBasePriceMatrix;
use AppleKlinika\Buyback\Application\Command\SaveDraftModelMinimumOffer;
use AppleKlinika\Buyback\Application\Command\SaveDraftQuestionnaireConditions;
use AppleKlinika\Buyback\Application\Command\SaveDraftBatteryBands;
use AppleKlinika\Buyback\Application\Command\SaveDraftOfferModeModifiers;
use AppleKlinika\Buyback\Application\Command\DeleteDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\ToggleDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPriceBookSettings;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPricingRule;
use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Exception\PriceBookHasBusinessReferencesException;
use AppleKlinika\Buyback\Application\Exception\PriceBookNotFoundException;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\ClonePriceBookToDraftHandler;
use AppleKlinika\Buyback\Application\Handler\DiscardDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\ProtectPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBasePriceMatrixHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftModelMinimumOfferHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftQuestionnaireConditionsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftBatteryBandsHandler;
use AppleKlinika\Buyback\Application\Handler\SaveDraftOfferModeModifiersHandler;
use AppleKlinika\Buyback\Application\Handler\DeleteDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\PreviewDraftPriceBookCalculationHandler;
use AppleKlinika\Buyback\Application\Handler\ToggleDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPriceBookSettingsHandler;
use AppleKlinika\Buyback\Application\Handler\UpdateDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Port\DeviceCatalogReader;
use AppleKlinika\Buyback\Application\Port\ActivePriceBookResolver;
use AppleKlinika\Buyback\Application\Port\Clock;
use AppleKlinika\Buyback\Application\Port\PriceBookRepository;
use AppleKlinika\Buyback\Application\Port\PricingRuleRepository;
use AppleKlinika\Buyback\Application\Port\PriceBookLifecycleRepository;
use AppleKlinika\Buyback\Application\LocalDemo\LocalDemoQuestionnaire;
use AppleKlinika\Buyback\Application\Pricing\DeviceCatalogItem;
use AppleKlinika\Buyback\Application\Pricing\OfferModeExampleCalculator;
use AppleKlinika\Buyback\Application\Pricing\PriceBookActivationReadinessService;
use AppleKlinika\Buyback\Application\Exception\MultipleActivePriceBooksException;
use AppleKlinika\Buyback\Application\Exception\NoActivePriceBookException;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
use AppleKlinika\Buyback\Domain\Exception\InvalidAggregateOperationException;
use AppleKlinika\Buyback\Domain\Exception\StaleAggregateVersionException;
use AppleKlinika\Buyback\Domain\Pricing\ConditionDefinition;
use AppleKlinika\Buyback\Domain\Pricing\CurrencyCode;
use AppleKlinika\Buyback\Domain\Pricing\MinimumOfferPolicy;
use AppleKlinika\Buyback\Domain\Pricing\PriceBook;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookId;
use AppleKlinika\Buyback\Domain\Pricing\PriceBookStatus;
use AppleKlinika\Buyback\Domain\Pricing\PricingCalculationResult;
use AppleKlinika\Buyback\Domain\Pricing\PricingOutcome;
use AppleKlinika\Buyback\Domain\Pricing\PricingRule;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleId;
use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\SystemDefaultQuestionnairePolicy;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeConfiguration;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeDefinition;
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;

final class PriceBooksPage
{
    public const SLUG = 'appleklinika-buyback-price-books';
    private const STORAGE_OPTIONS = [32, 64, 128, 256, 512, 1024];
    private const TAB_BASE_PRICES = 'base-prices';
    private const TAB_CONDITIONS = 'conditions';
    private const TAB_BATTERY = 'battery';
    private const TAB_OFFER_MODES = 'offer-modes';
    private const TAB_PREVIEW = 'preview';

    /** @var list<string> */
    private const EDITOR_TABS = [self::TAB_BASE_PRICES, self::TAB_CONDITIONS, self::TAB_BATTERY, self::TAB_OFFER_MODES, self::TAB_PREVIEW];

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly DeviceCatalogReader $catalog,
        private readonly CreateDraftPriceBookHandler $createBook,
        private readonly ClonePriceBookToDraftHandler $cloneBook,
        private readonly DiscardDraftPriceBookHandler $discardDraft,
        private readonly SaveDraftBasePriceMatrixHandler $saveBasePriceMatrix,
        private readonly SaveDraftModelMinimumOfferHandler $saveModelMinimumOffer,
        private readonly SaveDraftQuestionnaireConditionsHandler $saveQuestionnaireConditions,
        private readonly SaveDraftBatteryBandsHandler $saveBatteryBands,
        private readonly SaveDraftOfferModeModifiersHandler $saveOfferModeModifiers,
        private readonly OfferModeExampleCalculator $offerModeExamples,
        private readonly UpdateDraftPriceBookSettingsHandler $updateBook,
        private readonly AddDraftPricingRuleHandler $addRule,
        private readonly UpdateDraftPricingRuleHandler $updateRule,
        private readonly ToggleDraftPricingRuleHandler $toggleRule,
        private readonly DeleteDraftPricingRuleHandler $deleteRule,
        private readonly PricingRuleFormParser $ruleParser,
        private readonly PreviewDraftPriceBookCalculationHandler $previewHandler,
        private readonly PreviewCalculationFormParser $previewParser,
        private readonly PriceBookActivationReadinessService $readiness,
        private readonly ActivateDraftPriceBookHandler $activateBook,
        private readonly ActivePriceBookResolver $activePriceBookResolver,
        private readonly Clock $clock,
        private readonly AdminAuthorization $authorization,
        private readonly AdminSubmissionGuard $submissionGuard,
        private readonly LocalDemoQuestionnaire $questionnaire,
        private readonly ?PriceBookLifecycleRepository $lifecycle = null,
        private readonly ?ProtectPriceBookHandler $protectBook = null,
        private readonly ?OfferModeConfiguration $offerModes = null
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'handlePost']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        if (current_user_can('manage_woocommerce')) {
            add_submenu_page('woocommerce', 'Apple Klinika Buyback – Árkönyvek', 'Buyback – Árkönyvek', CapabilityManager::VIEW_PRICE_BOOKS, self::SLUG, [$this, 'render']);
            return;
        }
        add_menu_page('Apple Klinika Buyback – Árkönyvek', 'Buyback – Árkönyvek', CapabilityManager::VIEW_PRICE_BOOKS, self::SLUG, [$this, 'render'], 'dashicons-calculator', 58);
    }

    public function enqueueAssets(string $hook): void
    {
        if (! in_array($hook, ['woocommerce_page_' . self::SLUG, 'toplevel_page_' . self::SLUG], true)) {
            return;
        }
        $cssPath = APPLEKLINIKA_BUYBACK_PATH . '/assets/admin/price-books.css';
        $jsPath = APPLEKLINIKA_BUYBACK_PATH . '/assets/admin/price-books.js';
        wp_enqueue_style('appleklinika-buyback-admin', APPLEKLINIKA_BUYBACK_URL . 'assets/admin/price-books.css', [], md5_file($cssPath) ?: APPLEKLINIKA_BUYBACK_VERSION);
        wp_enqueue_script('appleklinika-buyback-admin', APPLEKLINIKA_BUYBACK_URL . 'assets/admin/price-books.js', [], md5_file($jsPath) ?: APPLEKLINIKA_BUYBACK_VERSION, true);
    }

    public function handlePost(): void
    {
        if (! isset($_POST['ak_buyback_action'])) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['ak_buyback_action']));
        if ($action === 'preview_calculation') {
            return;
        }
        $nonce = sanitize_text_field((string) wp_unslash($_POST['_ak_buyback_nonce'] ?? ''));

        try {
            $this->authorization->assert($this->capabilityForAction($action), $nonce);
            $this->dispatch($action, wp_unslash($_POST));
            $this->redirect('success', $action, $action === 'discard_draft_price_book' || $action === 'clone_active_price_book' ? 0 : $this->postedInt('price_book_id'), $this->postedTab(), null, $this->postedModel());
        } catch (\Throwable $exception) {
            $message = match ($action) {
                'save_questionnaire_conditions' => 'Az állapotlevonások mentése nem sikerült: ' . $exception->getMessage(),
                'save_battery_bands' => 'Az akkumulátorsávok mentése nem sikerült: ' . $exception->getMessage(),
                'save_offer_mode_modifiers' => 'Az ajánlattípusok mentése nem sikerült: ' . $exception->getMessage(),
                'discard_draft_price_book' => $this->discardErrorMessage($exception),
                'clone_active_price_book' => $this->cloneErrorMessage($exception),
                default => null,
            };
            $this->redirect('error', 'validation', $this->postedInt('price_book_id'), $this->postedTab(), $message, $this->postedModel());
        }
    }

    public function render(): void
    {
        if (! current_user_can(CapabilityManager::VIEW_PRICE_BOOKS)) {
            wp_die(esc_html('Nincs jogosultságod az árkönyvek kezeléséhez.'));
        }

        $bookId = isset($_GET['book_id']) ? absint($_GET['book_id']) : 0;
        $tab = $this->resolveTab();
        echo '<div class="wrap ak-buyback-admin">';
        echo '<h1>Apple Klinika Felvásárlás – Árkönyvek</h1>';
        $this->renderTabs($bookId, $tab);
        $this->renderActiveBookNotice();
        $this->renderNotice();

        if ($bookId > 0) {
            $this->renderEdit(new PriceBookId($bookId), $tab);
        } else {
            $this->renderIndex();
        }
        echo '</div>';
    }

    private function dispatch(string $action, array $post): void
    {
        if ($action === 'create_price_book') {
            $actorId = get_current_user_id();
            $token = sanitize_text_field((string) ($post['submission_token'] ?? ''));
            if (! $this->submissionGuard->consume($action, $token, $actorId)) {
                throw new \RuntimeException('Ezt a beküldést már feldolgoztuk.');
            }
            $book = $this->createBook->handle(new CreateDraftPriceBook(
                sanitize_text_field((string) ($post['label'] ?? '')),
                $this->requiredNonNegativeInt($post, 'minimum_offer_minor'),
                $this->requiredPositiveInt($post, 'rounding_increment_minor'),
                sanitize_key((string) ($post['minimum_policy'] ?? '')),
                $actorId
            ));
            $_POST['price_book_id'] = $book->id()?->toInt() ?? 0;
            return;
        }

        if ($action === 'clone_active_price_book') {
            $actorId = get_current_user_id();
            $token = sanitize_text_field((string) ($post['submission_token'] ?? ''));
            if (! $this->submissionGuard->consume($action, $token, $actorId)) {
                throw new \RuntimeException('Ezt a másolási kérést már feldolgoztuk.');
            }
            $clone = $this->cloneBook->handle(new ClonePriceBookToDraft(
                $this->requiredPositiveInt($post, 'source_price_book_id'),
                $this->requiredNonNegativeInt($post, 'expected_source_version'),
                $actorId
            ));
            set_transient('ak_buyback_lifecycle_notice_' . $actorId, [
                'type' => 'clone', 'label' => $clone->label(), 'id' => $clone->id()?->toInt() ?? 0,
            ], MINUTE_IN_SECONDS);
            return;
        }

        if ($action === 'discard_draft_price_book') {
            $deleted = $this->discardDraft->handle(new DiscardDraftPriceBook(
                $this->requiredPositiveInt($post, 'price_book_id'),
                sanitize_text_field((string) ($post['discard_confirmation'] ?? '')),
                get_current_user_id()
            ));
            set_transient('ak_buyback_lifecycle_notice_' . get_current_user_id(), ['type' => 'deletion'] + $deleted, MINUTE_IN_SECONDS);
            return;
        }

        if ($action === 'protect_price_book') {
            if ($this->protectBook === null) {
                throw new \RuntimeException('A védett referencia kezelése átmenetileg nem érhető el.');
            }
            $protected = $this->protectBook->handle(new ProtectPriceBook(
                $this->requiredPositiveInt($post, 'price_book_id'),
                get_current_user_id(),
                sanitize_text_field((string) ($post['protection_confirmation'] ?? ''))
            ));
            set_transient('ak_buyback_lifecycle_notice_' . get_current_user_id(), ['type' => 'protection'] + $protected, MINUTE_IN_SECONDS);
            return;
        }

        $bookId = $this->requiredPositiveInt($post, 'price_book_id');
        $bookVersion = $this->requiredNonNegativeInt($post, 'expected_book_version');

        if ($this->lifecycle?->isProtected(new PriceBookId($bookId))) {
            throw new \InvalidArgumentException('Védett referencia-árkönyv közvetlenül nem szerkeszthető. Készíts másolatot új piszkozathoz.');
        }

        if ($action === 'update_price_book') {
            $this->updateBook->handle(new UpdateDraftPriceBookSettings(
                $bookId,
                $bookVersion,
                sanitize_text_field((string) ($post['label'] ?? '')),
                $this->requiredNonNegativeInt($post, 'minimum_offer_minor'),
                $this->requiredPositiveInt($post, 'rounding_increment_minor'),
                sanitize_key((string) ($post['minimum_policy'] ?? ''))
            ));
            return;
        }

        if ($action === 'add_rule') {
            $this->addRule->handle(new AddDraftPricingRule($bookId, $bookVersion, $this->ruleParser->parse($post)));
            return;
        }

        if ($action === 'save_base_price_matrix') {
            $basePrices = isset($post['base_prices']) && is_array($post['base_prices']) ? $post['base_prices'] : [];
            $this->saveBasePriceMatrix->handle(new SaveDraftBasePriceMatrix($bookId, $bookVersion, $basePrices));
            return;
        }

        if ($action === 'save_model_minimum_offer') {
            $mode = sanitize_key((string) ($post['model_minimum_mode'] ?? ''));
            if (! in_array($mode, ['custom', 'inherit'], true)) {
                throw new \InvalidArgumentException('Érvénytelen modellminimum-beállítás.');
            }
            $this->saveModelMinimumOffer->handle(new SaveDraftModelMinimumOffer(
                $bookId,
                $bookVersion,
                sanitize_key((string) ($post['model_minimum_model_key'] ?? '')),
                $mode === 'custom' ? $this->requiredNonNegativeInt($post, 'model_minimum_amount') : null
            ));
            return;
        }

        if ($action === 'save_questionnaire_conditions') {
            $conditions = isset($post['questionnaire_conditions']) && is_array($post['questionnaire_conditions']) ? $post['questionnaire_conditions'] : [];
            $components = isset($post['service_history_components']) && is_array($post['service_history_components']) ? $post['service_history_components'] : [];
            $this->saveQuestionnaireConditions->handle(new SaveDraftQuestionnaireConditions($bookId, $bookVersion, sanitize_key((string) ($post['condition_model_key'] ?? '')), $conditions, $components));
            return;
        }

        if ($action === 'save_battery_bands') {
            $bands = isset($post['battery_bands']) && is_array($post['battery_bands']) ? array_values($post['battery_bands']) : [];
            $this->saveBatteryBands->handle(new SaveDraftBatteryBands($bookId, $bookVersion, sanitize_key((string) ($post['battery_model_key'] ?? '')), $bands));
            return;
        }

        if ($action === 'save_offer_mode_modifiers') {
            $modifiers = isset($post['offer_mode_modifiers']) && is_array($post['offer_mode_modifiers']) ? array_values($post['offer_mode_modifiers']) : [];
            $this->saveOfferModeModifiers->handle(new SaveDraftOfferModeModifiers($bookId, $bookVersion, $modifiers));
            return;
        }

        if ($action === 'activate_price_book') {
            $target = $this->books->getById(new PriceBookId($bookId));
            $previous = $this->books->list(1, 5, new PriceBookStatus(PriceBookStatus::ACTIVE))->items[0] ?? null;
            $activated = $this->activateBook->handle(new ActivateDraftPriceBook(
                $bookId,
                $bookVersion,
                get_current_user_id(),
                sanitize_text_field((string) ($post['activation_confirmation'] ?? ''))
            ));
            set_transient('ak_buyback_lifecycle_notice_' . get_current_user_id(), [
                'type' => 'activation', 'new_id' => $activated->id()?->toInt(), 'new_label' => $activated->label(),
                'previous_id' => $previous?->id()?->toInt(), 'previous_label' => $previous?->label(), 'currency' => $activated->currency()->code(),
            ], MINUTE_IN_SECONDS);
            return;
        }

        $ruleId = $this->requiredPositiveInt($post, 'rule_id');
        $ruleVersion = $this->requiredNonNegativeInt($post, 'expected_rule_version');
        if ($action === 'update_rule') {
            $this->updateRule->handle(new UpdateDraftPricingRule($bookId, $bookVersion, $ruleId, $ruleVersion, $this->ruleParser->parse($post)));
            return;
        }
        if ($action === 'toggle_rule') {
            $this->toggleRule->handle(new ToggleDraftPricingRule($bookId, $bookVersion, $ruleId, $ruleVersion, (string) ($post['enabled'] ?? '') === '1'));
            return;
        }
        if ($action === 'delete_rule') {
            $this->deleteRule->handle(new DeleteDraftPricingRule($bookId, $bookVersion, $ruleId, $ruleVersion));
            return;
        }

        throw new \InvalidArgumentException('Ismeretlen árkönyv művelet.');
    }

    private function capabilityForAction(string $action): string
    {
        return match ($action) {
            'activate_price_book' => CapabilityManager::ACTIVATE_PRICE_BOOKS,
            'discard_draft_price_book' => CapabilityManager::DELETE_PRICE_BOOK_DRAFTS,
            'protect_price_book' => CapabilityManager::PROTECT_PRICE_BOOKS,
            'create_price_book' => CapabilityManager::CREATE_PRICE_BOOK_DRAFTS,
            'clone_active_price_book' => CapabilityManager::CLONE_PRICE_BOOKS,
            default => CapabilityManager::EDIT_PRICE_BOOKS,
        };
    }

    private function renderIndex(): void
    {
        $active = $this->books->list(1, 5, new PriceBookStatus(PriceBookStatus::ACTIVE))->items;
        $drafts = $this->books->list(1, 50, new PriceBookStatus(PriceBookStatus::DRAFT))->items;
        $archived = $this->books->list(1, 20, new PriceBookStatus(PriceBookStatus::RETIRED))->items;
        usort($drafts, static fn (PriceBook $left, PriceBook $right): int => $right->updatedAt() <=> $left->updatedAt() ?: $right->id()?->toInt() <=> $left->id()?->toInt());
        echo '<p class="ak-pricebook-intro">Itt kezelheted a nyilvános felvásárlási kalkulátor árait és szabályait.</p>';
        $this->renderPriceBookTopSummary($active, $drafts);
        echo '<section class="ak-buyback-card ak-pricebook-section ak-pricebook-section--active"><h2>Jelenleg használt árkönyv</h2>';
        echo '<p>Ezt az árkönyvet használja jelenleg a felvásárlási kalkulátor.</p>';
        $this->renderPriceBookList($active, 'Jelenleg nincs aktív HUF árkönyv.', 'active');
        echo '</section><section class="ak-buyback-card ak-pricebook-section"><h2>Piszkozatok</h2>';
        echo '<p>Ezek az árkönyvek még szerkeszthetők. A vásárlóknak addig nem jelennek meg, amíg valamelyiket aktívvá nem teszed.</p>';
        $this->renderDraftFilters();
        $this->renderPriceBookList($drafts, 'Még nincs szerkeszthető piszkozat.', 'draft');
        echo '</section><section class="ak-buyback-card ak-pricebook-section ak-pricebook-section--retired"><details class="ak-previous-pricebooks"><summary>Korábban használt árkönyvek (' . esc_html((string) count($archived)) . ')</summary>';
        echo '<p>Ezek az árkönyvek már nem módosíthatók, de megtekinthetők és új piszkozat készíthető belőlük.</p>';
        $this->renderPriceBookList($archived, 'Nincs archivált árkönyv.', 'retired');
        echo '</details>';
        echo '</section>';
        echo '<section class="ak-buyback-card ak-advanced-create"><details><summary>Haladó beállítások</summary><h2>Üres árkönyv létrehozása</h2><p class="description">Az üres árkönyv nem másolja át a jelenlegi élő árakat és szabályokat. A normál munkafolyamathoz használd az „Új módosítás indítása” gombot.</p><form method="post">';
        $this->securityFields('create_price_book');
        echo '<input type="hidden" name="submission_token" value="' . esc_attr($this->submissionGuard->issue()) . '">';
        $this->textField('label', 'Megnevezés', '', true);
        echo '<p><label>Pénznem</label><input type="text" value="HUF" readonly></p>';
        $this->numberField('minimum_offer_minor', 'Minimum ajánlat (Ft)', 0, 0);
        $this->numberField('rounding_increment_minor', 'Kerekítési lépés (Ft)', 1000, 1);
        $this->policySelect(MinimumOfferPolicy::MANUAL_REVIEW);
        submit_button('Piszkozat létrehozása');
        echo '</form></details></section>';
    }

    /** @param list<PriceBook> $active @param list<PriceBook> $drafts */
    private function renderPriceBookTopSummary(array $active, array $drafts): void
    {
        $activeBook = $active[0] ?? null;
        $protectedBook = null;
        if ($this->lifecycle !== null) {
            $protectedId = $this->lifecycle->protectedReferenceFor(new CurrencyCode('HUF'));
            $protectedBook = $protectedId === null ? null : $this->books->getById($protectedId);
        }

        $activatable = 0;
        foreach ($drafts as $draft) {
            $rules = $this->rules->listForPriceBook($draft->id());
            if ($this->readiness->evaluate($draft, $rules, $this->clock->now())->ready) {
                ++$activatable;
            }
        }

        echo '<section class="ak-pricebook-top-summary" aria-label="Árkönyv áttekintés"><dl>';
        echo '<div><dt>Jelenleg aktív</dt><dd>' . esc_html($activeBook === null ? 'Nincs beállítva' : $this->ownerFacingTitle($activeBook, false)) . '</dd></div>';
        echo '<div><dt>Védett alapárkönyv</dt><dd>' . esc_html($protectedBook === null ? 'Nincs beállítva' : $this->ownerFacingTitle($protectedBook, false)) . '</dd></div>';
        echo '<div><dt>Piszkozatok</dt><dd>' . esc_html((string) count($drafts)) . '</dd></div>';
        echo '<div><dt>Aktiválható</dt><dd>' . esc_html((string) $activatable) . '</dd></div>';
        echo '<div><dt>Hiányos</dt><dd>' . esc_html((string) (count($drafts) - $activatable)) . '</dd></div>';
        echo '</dl></section>';
    }

    private function renderDraftFilters(): void
    {
        echo '<div class="ak-pricebook-draft-filters" data-ak-draft-filters><div class="ak-pricebook-filter-buttons" role="group" aria-label="Piszkozatok szűrése">';
        foreach (['all' => 'Összes', 'ready' => 'Aktiválható', 'incomplete' => 'Hiányos'] as $filter => $label) {
            echo '<button type="button" class="button' . ($filter === 'all' ? ' is-active' : '') . '" data-ak-draft-filter="' . esc_attr($filter) . '" aria-pressed="' . ($filter === 'all' ? 'true' : 'false') . '">' . esc_html($label) . '</button>';
        }
        echo '</div><label class="screen-reader-text" for="ak-pricebook-draft-search">Keresés név vagy azonosító alapján…</label><input id="ak-pricebook-draft-search" type="search" data-ak-draft-search-input placeholder="Keresés név vagy azonosító alapján…"></div>';
        echo '<p class="ak-pricebook-filter-empty" data-ak-draft-filter-empty hidden>Nincs a kiválasztott feltételeknek megfelelő piszkozat.</p>';
    }

    private function isLocalOnlyTestBook(PriceBook $book): bool
    {
        return preg_match('/(?:\blocal(?:\s+only)?\b|\bne\s+deployold\b)/iu', $book->label()) === 1;
    }

    private function conciseReadinessBlocker(\AppleKlinika\Buyback\Domain\Pricing\PriceBookActivationReadinessReport $readiness): string
    {
        $first = (string) $readiness->blockingIssues[0];
        if ($first === 'missing_base_price') {
            try {
                $missing = max(0, count($this->catalog->iPhoneConfigurations()) - $readiness->enabledBasePriceCount);
                return $missing > 0 ? $missing . ' alapár hiányzik.' : $this->readinessMessage($first);
            } catch (DeviceCatalogUnavailableException) {
                return $this->readinessMessage($first);
            }
        }
        return $this->readinessMessage($first);
    }

    /** @param list<PriceBook> $books */
    private function renderPriceBookList(array $books, string $emptyMessage, string $variant): void
    {
        echo '<div class="ak-pricebook-list ak-pricebook-list--' . esc_attr($variant) . '"' . ($variant === 'draft' ? ' data-ak-draft-list' : '') . '>';
        foreach ($books as $book) {
            $rules = $this->rules->listForPriceBook($book->id());
            $baseCount = $this->basePriceCount($rules);
            $isEmptyDraft = $baseCount === 0 && $book->status()->isDraft();
            $isProtected = $this->lifecycle?->isProtected($book->id()) ?? false;
            $readiness = $book->status()->isDraft() ? $this->readiness->evaluate($book, $rules, $this->clock->now()) : null;
            $statusClass = $book->status()->code();
            $confirmationPrefix = 'ak-pricebook-' . $book->id()->toInt();
            $protectedReference = $this->lifecycle?->protectedReferenceFor($book->currency());
            $replacesProtectedReference = $protectedReference !== null && ! $isProtected;
            $draftReadiness = $readiness?->ready ? 'ready' : 'incomplete';
            echo '<article class="ak-pricebook-entry ak-pricebook-entry--' . esc_attr($statusClass) . ' ak-pricebook-entry--' . esc_attr($variant) . '" data-ak-pricebook-card' . ($variant === 'draft' ? ' data-ak-draft-row data-ak-draft-readiness="' . esc_attr($draftReadiness) . '" data-ak-draft-search="' . esc_attr($book->label() . ' ' . $book->id()->toInt()) . '"' : '') . '><div class="ak-pricebook-entry-main">';
            echo '<span class="ak-status ak-status--' . esc_attr($statusClass) . '">' . esc_html($this->statusLabel($book)) . '</span>';
            if ($isProtected) {
                echo '<span class="ak-status ak-status--protected">Védett alapárkönyv</span>';
            }
            echo '<h3>' . esc_html($this->ownerFacingTitle($book, $isEmptyDraft)) . '</h3>';
            echo '<dl class="ak-pricebook-summary"><div><dt>Azonosító</dt><dd>#' . esc_html((string) $book->id()->toInt()) . '</dd></div><div><dt>Verzió</dt><dd>v' . esc_html((string) $book->versionNumber()->value()) . '</dd></div><div><dt>Pénznem</dt><dd>' . esc_html($book->currency()->code()) . '</dd></div><div><dt>Alapárak</dt><dd>' . esc_html((string) $baseCount) . '</dd></div><div><dt>Szabályok száma</dt><dd>' . esc_html((string) count($rules)) . '</dd></div>' . ($variant === 'draft' ? '<div><dt>Utoljára módosítva</dt><dd>' . esc_html($book->updatedAt()->format('Y-m-d H:i')) . '</dd></div>' : '') . '</dl>';
            if ($book->status()->isActive() && $this->isLocalOnlyTestBook($book)) {
                echo '<p class="ak-pricebook-warning">Tesztárakat tartalmaz – éles ajánlatadáshoz nem használható.</p>';
            }
            if ($isProtected) {
                echo '<p class="ak-pricebook-protection-help">Ez az árkönyv nem törölhető és közvetlenül nem szerkeszthető. Új piszkozat azonban bármikor készíthető belőle.</p>';
            }
            if ($isEmptyDraft) {
                echo '<p class="ak-pricebook-warning">Ez a módosítás nem örökölte az élő árkönyv árait és szabályait.</p>';
            } elseif (! $book->status()->isActive()) {
                echo '<p class="ak-pricebook-meta">' . esc_html($baseCount === 0 ? 'Nincs megadott alapár.' : $baseCount . ' alapár megadva.') . '</p>';
            }
            if ($book->status()->isDraft() && $readiness !== null && ! $readiness->ready && $readiness->blockingIssues !== []) {
                echo '<p class="ak-pricebook-blocker">' . esc_html($this->conciseReadinessBlocker($readiness)) . '</p>';
            }
            $deletionBlocker = $book->status()->isDraft() ? $this->draftDeletionBlocker($book, $isProtected) : null;
            $canDeleteDraft = $book->status()->isDraft() && $deletionBlocker === null && current_user_can(CapabilityManager::DELETE_PRICE_BOOK_DRAFTS);
            $canCloneDraft = $book->status()->isDraft() && current_user_can(CapabilityManager::CLONE_PRICE_BOOKS);
            $canProtect = current_user_can(CapabilityManager::PROTECT_PRICE_BOOKS);
            $showMoreActions = $canProtect || $canDeleteDraft || $canCloneDraft;
            echo '</div><div class="ak-pricebook-entry-actions">';
            if ($book->status()->isActive()) {
                echo '<a class="button button-primary" href="' . esc_url($this->editUrl($book->id())) . '">Árkönyv megnyitása</a> ';
                if (current_user_can(CapabilityManager::CLONE_PRICE_BOOKS)) {
                    echo '<form method="post" class="ak-inline-form">';
                    $this->securityFields('clone_active_price_book');
                    echo '<input type="hidden" name="submission_token" value="' . esc_attr($this->submissionGuard->issue()) . '">';
                    echo '<input type="hidden" name="source_price_book_id" value="' . esc_attr((string) $book->id()->toInt()) . '"><input type="hidden" name="expected_source_version" value="' . esc_attr((string) $book->version()->value()) . '">';
                    echo '<button type="submit" class="button">Új piszkozat készítése ebből</button></form>';
                }
            } elseif ($book->status()->isDraft()) {
                if (current_user_can(CapabilityManager::EDIT_PRICE_BOOKS) && ! $isProtected) {
                    echo '<a class="button button-primary" href="' . esc_url($this->editUrl($book->id())) . '">Szerkesztés folytatása</a>';
                } else {
                    echo '<a class="button" href="' . esc_url($this->editUrl($book->id())) . '">Árkönyv megnyitása</a>';
                }
                if ($readiness->ready) {
                    echo '<span class="ak-status ak-status--ready">Készen áll az aktiválásra</span>';
                    if (current_user_can(CapabilityManager::ACTIVATE_PRICE_BOOKS) && ! $isProtected) {
                        echo '<button type="button" class="button" data-ak-confirmation-trigger data-ak-confirmation-target="' . esc_attr($confirmationPrefix . '-activation') . '" aria-expanded="false">Aktiválás</button>';
                        echo '<section class="ak-pricebook-confirmation" id="' . esc_attr($confirmationPrefix . '-activation') . '" data-ak-confirmation-panel hidden><h4>Árkönyv használatba vétele</h4><p>Biztosan ezt az árkönyvet szeretnéd aktívvá tenni? A jelenleg használt árkönyv automatikusan a korábban használt árkönyvek közé kerül.</p><form method="post">';
                        $this->securityFields('activate_price_book', $book);
                        echo '<input type="hidden" name="price_book_id" value="' . esc_attr((string) $book->id()->toInt()) . '"><input type="hidden" name="expected_book_version" value="' . esc_attr((string) $book->version()->value()) . '"><label>A megerősítéshez írd be az árkönyv pontos nevét:<input type="text" name="activation_confirmation" required></label><div class="ak-pricebook-confirmation-actions"><button type="submit" class="button button-primary">Beállítás aktív árkönyvként</button><button type="button" class="button-link" data-ak-confirmation-cancel>Mégse</button></div></form></section>';
                    }
                } else {
                    echo '<span class="ak-status ak-status--not-ready">' . esc_html($isProtected ? 'Védett alapárkönyv' : 'Még nem aktiválható') . '</span>';
                }
                if ($deletionBlocker !== null) {
                    echo '<p class="ak-pricebook-action-help">' . esc_html($deletionBlocker) . '</p>';
                }
            } else {
                echo '<a class="button" href="' . esc_url($this->editUrl($book->id())) . '">Árkönyv megnyitása</a>';
            }
            if ($book->status()->isRetired() && current_user_can(CapabilityManager::CLONE_PRICE_BOOKS)) {
                echo '<form method="post" class="ak-inline-form">';
                $this->securityFields('clone_active_price_book');
                echo '<input type="hidden" name="submission_token" value="' . esc_attr($this->submissionGuard->issue()) . '"><input type="hidden" name="source_price_book_id" value="' . esc_attr((string) $book->id()->toInt()) . '"><input type="hidden" name="expected_source_version" value="' . esc_attr((string) $book->version()->value()) . '"><button type="submit" class="button">Új piszkozat készítése ebből</button></form>';
            }
            if ($showMoreActions) {
                echo '<div class="ak-pricebook-more" data-ak-more-actions><button type="button" class="button" data-ak-more-trigger aria-expanded="false" aria-haspopup="menu">További műveletek</button><div class="ak-pricebook-more-menu" data-ak-more-menu role="menu" hidden>';
                if ($canCloneDraft) {
                    echo '<form method="post" class="ak-pricebook-more-form">';
                    $this->securityFields('clone_active_price_book');
                    echo '<input type="hidden" name="submission_token" value="' . esc_attr($this->submissionGuard->issue()) . '"><input type="hidden" name="source_price_book_id" value="' . esc_attr((string) $book->id()->toInt()) . '"><input type="hidden" name="expected_source_version" value="' . esc_attr((string) $book->version()->value()) . '"><button type="submit" class="button-link" role="menuitem">Új piszkozat készítése ebből</button></form>';
                }
                if ($canProtect) {
                    $protectionAction = $replacesProtectedReference ? 'Védett alap áthelyezése ide' : 'Beállítás védett alapárkönyvként';
                    echo '<button type="button" class="button-link" role="menuitem" data-ak-confirmation-trigger data-ak-confirmation-target="' . esc_attr($confirmationPrefix . '-protection') . '" aria-expanded="false">' . esc_html($protectionAction) . '</button>';
                }
                if ($canDeleteDraft) {
                    echo '<button type="button" class="button-link-delete" role="menuitem" data-ak-confirmation-trigger data-ak-confirmation-target="' . esc_attr($confirmationPrefix . '-deletion') . '" aria-expanded="false">Piszkozat törlése</button>';
                }
                echo '</div></div>';
            }
            if ($canDeleteDraft) {
                echo '<section class="ak-pricebook-confirmation ak-pricebook-confirmation--danger" id="' . esc_attr($confirmationPrefix . '-deletion') . '" data-ak-confirmation-panel hidden><h4>Piszkozat törlése</h4><p>A piszkozat és a hozzá tartozó felvásárlási szabályok véglegesen törlődnek. Ez a művelet nem vonható vissza.</p><p><strong>Árkönyv:</strong> ' . esc_html($book->label()) . ' (#' . esc_html((string) $book->id()?->toInt()) . ')</p><form method="post">';
                $this->securityFields('discard_draft_price_book', $book);
                echo '<label>A megerősítéshez írd be pontosan ezt: <strong>' . esc_html(DiscardDraftPriceBookHandler::CONFIRMATION_TOKEN) . '</strong><input type="text" name="discard_confirmation" required></label><div class="ak-pricebook-confirmation-actions"><button type="submit" class="button-link-delete">Piszkozat végleges törlése</button><button type="button" class="button-link" data-ak-confirmation-cancel>Mégse</button></div></form></section>';
            }
            if ($canProtect) {
                $protectionAction = $replacesProtectedReference ? 'Védett alap áthelyezése ide' : 'Beállítás védett alapárkönyvként';
                echo '<section class="ak-pricebook-confirmation" id="' . esc_attr($confirmationPrefix . '-protection') . '" data-ak-confirmation-panel hidden><h4>Védett alapárkönyv beállítása</h4><p>Ez lesz a stabil kiinduló árkönyv. Nem lehet majd törölni vagy közvetlenül szerkeszteni, de új piszkozat bármikor készíthető belőle.</p>';
                if ($replacesProtectedReference) {
                    echo '<p>A jelenlegi védett alapárkönyv védelme megszűnik.</p>';
                }
                echo '<form method="post">';
                $this->securityFields('protect_price_book', $book);
                echo '<input type="hidden" name="price_book_id" value="' . esc_attr((string) $book->id()->toInt()) . '"><label>A megerősítéshez írd be az árkönyv pontos nevét:<input type="text" name="protection_confirmation" required></label><div class="ak-pricebook-confirmation-actions"><button type="submit" class="button">Beállítás védett alapárkönyvként</button><button type="button" class="button-link" data-ak-confirmation-cancel>Mégse</button></div></form></section>';
            }
            echo '</div><details class="ak-pricebook-technical"><summary>Részletes adatok</summary><dl>';
            echo '<dt>Árkönyv neve</dt><dd>' . esc_html($book->label()) . '</dd>';
            echo '<dt>Állapot</dt><dd>' . esc_html($this->statusLabel($book)) . '</dd>';
            echo '<dt>Verzió</dt><dd>v' . esc_html((string) $book->versionNumber()->value()) . '</dd>';
            echo '<dt>Azonosító</dt><dd>' . esc_html((string) $book->id()->toInt()) . '</dd>';
            echo '<dt>Pénznem</dt><dd>' . esc_html($book->currency()->code()) . '</dd>';
            echo '<dt>Szabályok száma</dt><dd>' . esc_html((string) count($rules)) . '</dd>';
            echo '<dt>Aktiválhatóság</dt><dd>' . esc_html($readiness !== null ? ($readiness->ready ? 'Aktiválható' : 'Még nem aktiválható') : 'Nem értelmezhető') . '</dd>';
            if ($readiness !== null && $readiness->blockingIssues !== []) {
                echo '<dt>Az aktiválás feltételei</dt><dd><ul class="ak-pricebook-readiness-details">';
                foreach ($readiness->blockingIssues as $issue) {
                    echo '<li>' . esc_html($this->readinessMessage((string) $issue)) . '</li>';
                }
                echo '</ul></dd>';
            }
            echo '<dt>Létrehozva</dt><dd>' . esc_html($book->createdAt()->format('Y-m-d H:i')) . '</dd>';
            echo '<dt>Utoljára módosítva</dt><dd>' . esc_html($book->updatedAt()->format('Y-m-d H:i')) . '</dd>';
            echo '</dl></details></article>';
        }
        if ($books === []) {
            echo '<p class="ak-pricebook-empty">' . esc_html($emptyMessage) . '</p>';
        }
        echo '</div>';
    }

    private function draftDeletionBlocker(PriceBook $book, bool $isProtected): ?string
    {
        if ($isProtected) return 'A védett alapárkönyv nem törölhető.';
        if ($this->lifecycle?->hasEverBeenActive($book->id())) return 'Ezt az árkönyvet korábban már használták, ezért az előzmények megőrzése miatt nem törölhető.';
        if ($this->lifecycle?->hasLifecycleDependencies($book->id())) return 'Az árkönyvre mentett felvásárlási igény hivatkozik.';
        return null;
    }

    private function ownerFacingTitle(PriceBook $book, bool $isEmptyDraft): string
    {
        if ($isEmptyDraft) {
            return 'Üres módosítás';
        }

        $label = $book->label();
        if (! preg_match('/(?:local\\s+demo|másolat|\\btest\\b|^noj$)/iu', $label)) {
            return $label;
        }

        $version = 'v' . $book->versionNumber()->value();
        return match ($book->status()->code()) {
            PriceBookStatus::ACTIVE => 'Felvásárlási árkönyv – ' . $version,
            PriceBookStatus::DRAFT => 'Módosítás alatt – ' . $version,
            PriceBookStatus::RETIRED => 'Korábbi árkönyv – ' . $version,
            default => $label,
        };
    }

    private function renderEdit(PriceBookId $id, string $tab): void
    {
        $book = $this->books->getById($id);
        if ($book === null) {
            echo '<div class="notice notice-error"><p>Az árkönyv nem található.</p></div>';
            return;
        }
        $rules = $this->rules->listForPriceBook($id);
        $isProtected = $this->lifecycle?->isProtected($id) ?? false;
        $canEdit = current_user_can(CapabilityManager::EDIT_PRICE_BOOKS);
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">← Vissza az árkönyvekhez</a></p>';
        $lifecycleText = $book->status()->isDraft()
            ? 'Szerkeszthető piszkozat. Az itt mentett változtatások aktiválásig nem érintik a nyilvános felvásárlási árazást.'
            : ($book->status()->isActive() ? 'Az aktív árkönyv és szabályai csak olvashatók.' : 'Az archivált árkönyv és szabályai változatlan előzményként megmaradnak.');
        echo '<div class="ak-buyback-heading"><div><h2>v' . esc_html((string) $book->versionNumber()->value()) . ' – ' . esc_html($book->label()) . '</h2><span class="ak-status">' . esc_html($this->statusLabel($book)) . '</span></div><p>' . esc_html($lifecycleText) . '</p></div>';

        if ($book->status()->isDraft() && $this->basePriceCount($rules) === 0) {
            echo '<div class="notice notice-warning inline"><p>Üres piszkozat – nem örökölte az aktív árkönyv árait.</p></div>';
        }

        if (! $book->status()->isDraft() || $isProtected || ! $canEdit) {
            $message = $isProtected
                ? 'Ez az árkönyv nem törölhető és közvetlenül nem szerkeszthető. Új piszkozat azonban bármikor készíthető belőle.'
                : (! $book->status()->isDraft() ? 'Az aktív és a korábban használt árkönyvek nem szerkeszthetők közvetlenül. Új piszkozathoz készíts másolatot.' : 'Ezt az árkönyvet csak megtekintheted.');
            echo '<div class="notice notice-info inline"><p>' . esc_html($message) . '</p></div>';
            if ($tab === self::TAB_BASE_PRICES) {
                $this->renderBasePriceMatrix($book, $rules, true, $tab);
            } else {
                $this->renderReadOnlyTabPlaceholder($tab, $book, $rules);
            }
            return;
        }

        if ($tab === self::TAB_BASE_PRICES) {
            $this->renderBasePricesTab($book, $rules, $tab);
            return;
        }
        if ($tab === self::TAB_CONDITIONS) {
            $this->renderConditionsTab($book, $rules, false, $tab);
            return;
        }
        if ($tab === self::TAB_BATTERY) {
            $this->renderBatteryTab($book, $rules, false, $tab);
            return;
        }
        if ($tab === self::TAB_OFFER_MODES) {
            $this->renderOfferModesTab($book, $rules, false, $tab);
            return;
        }
        $this->renderCalculationPreview($book, $tab);
    }

    /** @param list<PricingRule> $rules */
    private function renderBasePricesTab(PriceBook $book, array $rules, string $tab): void
    {
        $this->renderModelMinimumOfferEditor($book, $rules, $tab);
        $this->renderBasePriceMatrix($book, $rules, false, $tab);
    }

    /** @param list<PricingRule> $rules */
    private function renderModelMinimumOfferEditor(PriceBook $book, array $rules, string $tab): void
    {
        try {
            $models = $this->catalog->iPhoneModels();
        } catch (DeviceCatalogUnavailableException $exception) {
            echo '<section class="ak-buyback-card"><h3>Automatikus ajánlat minimuma</h3><div class="notice notice-error inline"><p>Az inventory készülékkatalógus nem érhető el, ezért a modellminimum nem szerkeszthető.</p></div></section>';
            return;
        }
        $model = $this->selectedConditionModel($models);
        if ($model === null) {
            echo '<section class="ak-buyback-card"><h3>Automatikus ajánlat minimuma</h3><div class="notice notice-error inline"><p>A megadott iPhone modell nem szerepel az inventory készülékkatalógusban.</p></div></section>';
            return;
        }

        $matches = array_values(array_filter($rules, static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::MINIMUM_OFFER && $rule->definition()->modelKey === $model->modelKey));
        if (count($matches) > 1) {
            echo '<section class="ak-buyback-card"><h3>Automatikus ajánlat minimuma</h3><div class="notice notice-error inline"><p>Ehhez a modellhez több minimumszabály tartozik. A beállítás biztonsági okból nem módosítható.</p></div></section>';
            return;
        }
        $override = $matches[0] ?? null;
        $amount = $override?->definition()->amount?->amount();

        echo '<section class="ak-buyback-card ak-model-minimum-offer"><h3>Automatikus ajánlat minimuma</h3>';
        echo '<p>Modellhez külön minimumot adhatsz meg. Ha a feltételek szerinti összeg ezt eléri vagy alá csökken, a készülék személyes bevizsgálásra kerül. A globális árkönyvminimum továbbra is érvényes marad.</p>';
        echo '<form method="get" class="ak-condition-model-selector"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="book_id" value="' . esc_attr((string) $book->id()?->toInt()) . '"><input type="hidden" name="tab" value="' . esc_attr($tab) . '"><label for="ak-model-minimum-model">Modell<select name="model" id="ak-model-minimum-model">';
        foreach ($models as $item) {
            echo '<option value="' . esc_attr($item->modelKey) . '" ' . selected($model->modelKey, $item->modelKey, false) . '>' . esc_html($item->label) . '</option>';
        }
        echo '</select></label><button type="submit" class="button">Modell betöltése</button></form>';
        echo '<p><strong>' . esc_html($model->label) . '</strong> · ' . esc_html($amount === null ? 'Alapbeállítás használata: ' . number_format_i18n($book->minimumOffer()->amount()) . ' Ft' : 'Saját minimum: ' . number_format_i18n($amount) . ' Ft') . '</p>';
        echo '<form method="post" class="ak-model-minimum-form">';
        $this->securityFields('save_model_minimum_offer', $book);
        $this->tabField($tab);
        echo '<input type="hidden" name="model_minimum_model_key" value="' . esc_attr($model->modelKey) . '"><input type="hidden" name="model_minimum_mode" value="custom"><label for="ak-model-minimum-amount">Saját minimum (Ft)</label><div class="ak-price-input"><input type="number" min="0" step="1" inputmode="numeric" id="ak-model-minimum-amount" name="model_minimum_amount" value="' . esc_attr($amount === null ? '' : (string) $amount) . '" required><span>Ft</span></div><button type="submit" class="button button-primary">Saját minimum mentése</button></form>';
        if ($override !== null) {
            echo '<form method="post" class="ak-inline-form">';
            $this->securityFields('save_model_minimum_offer', $book);
            $this->tabField($tab);
            echo '<input type="hidden" name="model_minimum_model_key" value="' . esc_attr($model->modelKey) . '"><input type="hidden" name="model_minimum_mode" value="inherit"><button type="submit" class="button">Alapbeállítás visszaállítása</button></form>';
        }
        echo '</section>';
    }

    /** @param list<PricingRule> $rules */
    private function renderBasePriceMatrix(PriceBook $book, array $rules, bool $readOnly, string $tab): void
    {
        try {
            $configurations = $this->catalog->iPhoneConfigurations();
        } catch (DeviceCatalogUnavailableException $exception) {
            echo '<section class="ak-buyback-card"><h3>Alapárak</h3><div class="notice notice-error inline"><p>Az inventory készülékkatalógus nem érhető el, ezért az alapár-mátrix nem jeleníthető meg.</p></div></section>';
            return;
        }
        if ($configurations === []) {
            echo '<section class="ak-buyback-card"><h3>Alapárak</h3><div class="notice notice-warning inline"><p>Az inventory katalógusban még nincs árazható iPhone modell–tárhely konfiguráció.</p></div></section>';
            return;
        }

        $models = [];
        $storages = [];
        foreach ($configurations as $configuration) {
            $models[$configuration->modelKey]['label'] = $configuration->modelLabel;
            $models[$configuration->modelKey]['storages'][$configuration->storageGb] = true;
            $storages[$configuration->storageGb] = true;
        }
        uasort($models, static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
        $storages = array_keys($storages);
        sort($storages, SORT_NUMERIC);

        $baseRules = [];
        $duplicateTargets = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->kind->code() !== PricingRuleKind::BASE_PRICE || $definition->modelKey === null || $definition->storage === null) {
                continue;
            }
            $key = $this->basePriceKey($definition->modelKey, $definition->storage->gigabytes());
            if (! isset($models[$definition->modelKey]['storages'][$definition->storage->gigabytes()])) {
                continue;
            }
            if (isset($baseRules[$key])) {
                $duplicateTargets[$key] = true;
            }
            $baseRules[$key] = $rule;
        }
        $configured = count($baseRules);
        $total = count($configurations);
        $missing = max(0, $total - $configured);

        echo '<section class="ak-buyback-card ak-base-price-matrix"><h3>Alapár-mátrix</h3>';
        echo '<p>' . ($readOnly ? 'A történeti árkönyv alapárai csak olvashatók.' : 'Minden mező egy egész HUF összeg. Az üres mezőhöz nem tartozik alapár-szabály; a mentés csak ezt a piszkozatot módosítja.') . '</p>';
        echo '<p class="description ak-inventory-summary">Inventory: ' . esc_html((string) count($models)) . ' iPhone-modell, ' . esc_html((string) count($configurations)) . ' érvényes konfiguráció</p>';
        echo '<p class="ak-matrix-summary"><strong><span data-ak-configured-count>' . esc_html((string) $configured) . '</span> / ' . esc_html((string) $total) . '</strong> konfiguráció árazva · <strong><span data-ak-missing-count>' . esc_html((string) $missing) . '</span></strong> hiányzik</p>';
        if ($duplicateTargets !== []) {
            echo '<div class="notice notice-error inline"><p>Egy vagy több modell–tárhely párhoz több alapár-szabály tartozik. A mátrix mentése addig nem lehetséges, amíg ezeket külön nem rendezik.</p></div>';
        }
        echo '<div class="ak-matrix-controls"><label for="ak-matrix-search">Modell keresése</label><input type="search" id="ak-matrix-search" data-ak-matrix-search placeholder="Például iPhone 15"><label><input type="checkbox" data-ak-missing-only> Csak a hiányzó árak</label></div>';
        if (! $readOnly && $duplicateTargets === []) {
            echo '<form method="post" data-ak-base-price-form>';
            $this->securityFields('save_base_price_matrix', $book);
            $this->tabField($tab);
        }
        echo '<p class="ak-matrix-empty" data-ak-matrix-empty hidden>Nincs a katalógusban a keresésnek megfelelő iPhone-modell.</p>';
        echo '<div class="ak-matrix-scroll" data-ak-matrix-table><table class="widefat striped"><thead><tr><th>Modell</th>';
        foreach ($storages as $storage) {
            echo '<th>' . esc_html($this->storageLabel($storage)) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($models as $modelKey => $model) {
            $rowMissing = false;
            foreach (array_keys($model['storages']) as $storage) {
                if (! isset($baseRules[$this->basePriceKey($modelKey, (int) $storage)])) {
                    $rowMissing = true;
                    break;
                }
            }
            echo '<tr data-ak-matrix-row data-ak-model-label="' . esc_attr((string) $model['label']) . '" data-ak-row-missing="' . ($rowMissing ? '1' : '0') . '"><th scope="row">' . esc_html((string) $model['label']) . '</th>';
            foreach ($storages as $storage) {
                if (! isset($model['storages'][$storage])) {
                    echo '<td class="ak-matrix-na">—</td>';
                    continue;
                }
                $rule = $baseRules[$this->basePriceKey($modelKey, $storage)] ?? null;
                $amount = $rule?->definition()->amount?->amount();
                if ($readOnly) {
                    echo '<td class="ak-matrix-price">' . esc_html($amount === null ? '—' : number_format_i18n($amount) . ' Ft') . '</td>';
                    continue;
                }
                echo '<td><label class="screen-reader-text" for="base_price_' . esc_attr($modelKey . '_' . $storage) . '">' . esc_html((string) $model['label'] . ' ' . $this->storageLabel($storage)) . '</label><div class="ak-price-input"><input type="number" min="0" step="1" inputmode="numeric" id="base_price_' . esc_attr($modelKey . '_' . $storage) . '" name="base_prices[' . esc_attr($modelKey) . '][' . esc_attr((string) $storage) . ']" value="' . esc_attr($amount === null ? '' : (string) $amount) . '" data-ak-base-price><span>Ft</span></div></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        if (! $readOnly && $duplicateTargets === []) {
            submit_button('Alapárak mentése', 'primary', 'submit', false, ['data-ak-save-base-prices' => '']);
            echo '</form>';
        }
        echo '</section>';
    }

    /** @param list<PricingRule> $rules */
    private function renderConditionsTab(PriceBook $book, array $rules, bool $readOnly, string $tab): void
    {
        try {
            $models = $this->catalog->iPhoneModels();
        } catch (DeviceCatalogUnavailableException $exception) {
            echo '<section class="ak-buyback-card"><h3>Állapotlevonások</h3><div class="notice notice-error inline"><p>Az inventory készülékkatalógus nem érhető el, ezért a modellhez tartozó állapotlevonások nem jeleníthetők meg.</p></div></section>';
            return;
        }
        $model = $this->selectedConditionModel($models);
        if ($model === null) {
            echo '<section class="ak-buyback-card"><h3>Állapotlevonások</h3><div class="notice notice-error inline"><p>A megadott iPhone modell nem szerepel az inventory készülékkatalógusban.</p></div></section>';
            return;
        }
        $rulesByCode = [];
        foreach ($rules as $rule) {
            $rulesByCode[$rule->definition()->code->code()] = $rule;
        }
        $questions = $this->questionnaire->conditionEditorQuestions();
        $componentMetadata = $this->questionnaire->serviceHistoryComponentRuleMetadata();
        $summary = ['total' => 0, 'configured' => 0, 'manual' => 0, 'reject' => 0];
        foreach ($questions as $question) {
            foreach ($question['options'] as $option) {
                if (! $option['configurable']) {
                    continue;
                }
                ++$summary['total'];
                $rule = $rulesByCode[SaveDraftQuestionnaireConditionsHandler::ruleCode($book->id()?->toInt() ?? 0, $model->modelKey, $question['question_key'], $option['answer_key'])] ?? null;
                $legacy = $rulesByCode[SaveDraftQuestionnaireConditionsHandler::legacyRuleCode($book->id()?->toInt() ?? 0, $question['question_key'], $option['answer_key'])] ?? null;
                $action = $rule === null ? SaveDraftQuestionnaireConditionsHandler::ACTION_SYSTEM_DEFAULT : $this->conditionAction($rule);
                if ($action !== SaveDraftQuestionnaireConditionsHandler::ACTION_SYSTEM_DEFAULT) {
                    ++$summary['configured'];
                }
                if ($action === SaveDraftQuestionnaireConditionsHandler::ACTION_MANUAL_REVIEW) {
                    ++$summary['manual'];
                }
                if ($action === SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT) {
                    ++$summary['reject'];
                }
            }
        }

        echo '<section class="ak-buyback-card ak-conditions-editor"><h3>Állapotlevonások beállítása ehhez a modellhez</h3>';
        echo '<form method="get" class="ak-condition-model-selector" data-ak-condition-model-form><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="book_id" value="' . esc_attr((string) $book->id()?->toInt()) . '"><input type="hidden" name="tab" value="' . esc_attr($tab) . '"><label for="ak-condition-model">Modell<select name="model" id="ak-condition-model" data-ak-condition-model-select data-ak-current-value="' . esc_attr($model->modelKey) . '">';
        foreach ($models as $item) {
            echo '<option value="' . esc_attr($item->modelKey) . '" ' . selected($model->modelKey, $item->modelKey, false) . '>' . esc_html($item->label) . '</option>';
        }
        echo '</select></label><button type="submit" class="button">Modell betöltése</button></form>';
        echo '<p>' . esc_html($readOnly ? 'Az árkönyv állapotlevonásai csak olvashatók.' : 'A nyilvános felvásárlási kérdőív válaszaihoz itt üzleti következményt adhatsz. A mentés kizárólag ennek a piszkozatnak a kérdőív-alapú állapotszabályait módosítja.') . '</p>';
        echo '<div class="ak-condition-summary"><strong>' . esc_html($model->label) . '</strong> · ' . esc_html($this->modelBasePriceStatus($model, $rules)) . '<br><strong><span data-ak-condition-configured>' . esc_html((string) $summary['configured']) . '</span> / <span data-ak-condition-total>' . esc_html((string) $summary['total']) . '</span></strong> válasz beállítva · <strong><span data-ak-condition-unconfigured>' . esc_html((string) ($summary['total'] - $summary['configured'])) . '</span></strong> nincs beállítva · Kézi bevizsgálás: <strong><span data-ak-condition-manual>' . esc_html((string) $summary['manual']) . '</span></strong> · Nem vásároljuk fel: <strong><span data-ak-condition-reject>' . esc_html((string) $summary['reject']) . '</span></strong></div>';
        echo '<div class="notice notice-info inline"><p><strong>Akkumulátor:</strong> az akkumulátorállapot külön kérdés, dedikált szerkesztője ezen a fülön nem érhető el.</p></div>';
        if (! $readOnly) {
            echo '<form method="post" data-ak-condition-form>';
            $this->securityFields('save_questionnaire_conditions', $book);
            $this->tabField($tab);
            echo '<input type="hidden" name="condition_model_key" value="' . esc_attr($model->modelKey) . '"><div class="ak-condition-save ak-condition-save-top"><span data-ak-condition-changes aria-live="polite">Nincs mentetlen változás.</span><button type="submit" class="button button-primary">Módosítások mentése – ' . esc_html($model->label) . '</button></div>';
        }

        $lastPanel = null;
        foreach ($questions as $question) {
            if ($lastPanel !== $question['panel']) {
                if ($lastPanel !== null) {
                    echo '</div></section>';
                }
                echo '<section class="ak-condition-panel"><h4>' . esc_html($question['panel_title']) . '</h4><div class="ak-condition-questions">';
                $lastPanel = $question['panel'];
            }
            echo '<div class="ak-condition-question"><h5>' . esc_html($question['label']) . '</h5>';
            if ($question['helper'] !== '') {
                echo '<p class="description">' . esc_html($question['helper']) . '</p>';
            }
            echo '<div class="ak-condition-rows">';
            foreach ($question['options'] as $option) {
                if (! $option['configurable']) {
                    $isSafety = ($option['editor_kind'] ?? '') === 'safety';
                    $label = $isSafety ? 'Rögzített biztonsági szabály' : 'Tájékoztató válasz';
                    $class = $isSafety ? ' ak-condition-system--safety' : ' ak-condition-system--informational';
                    echo '<div class="ak-condition-row ak-condition-system' . esc_attr($class) . '"><div><strong>' . esc_html($option['label']) . '</strong><p><span class="ak-system-label">' . esc_html($label) . '</span> ' . esc_html($option['system_outcome'] ?? '') . '</p></div></div>';
                    continue;
                }
                $rule = $rulesByCode[SaveDraftQuestionnaireConditionsHandler::ruleCode($book->id()?->toInt() ?? 0, $model->modelKey, $question['question_key'], $option['answer_key'])] ?? null;
                $legacy = $rulesByCode[SaveDraftQuestionnaireConditionsHandler::legacyRuleCode($book->id()?->toInt() ?? 0, $question['question_key'], $option['answer_key'])] ?? null;
                $action = $rule === null ? SaveDraftQuestionnaireConditionsHandler::ACTION_SYSTEM_DEFAULT : $this->conditionAction($rule);
                $value = $this->conditionValue($rule, $action);
                echo '<div class="ak-condition-row" data-ak-condition-row data-ak-condition-original-action="' . esc_attr($action) . '" data-ak-condition-original-value="' . esc_attr($value === null ? '' : (string) $value) . '"><div class="ak-condition-answer"><strong>' . esc_html($option['label']) . '</strong></div>';
                if ($readOnly) {
                    echo '<div class="ak-condition-current">' . esc_html($this->conditionActionLabel($action, $value)) . '<p>' . esc_html($this->conditionSourceLabel($rule, $legacy, $question['question_key'], $option['answer_key'])) . '</p></div>';
                } else {
                    $name = 'questionnaire_conditions[' . $question['question_key'] . '][' . $option['answer_key'] . ']';
                    $needsValue = in_array($action, [SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED, SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE], true);
                    echo '<label class="ak-condition-action">Művelet<select name="' . esc_attr($name) . '[action]" data-ak-condition-action>';
                    foreach ($this->conditionActions() as $actionKey => $actionLabel) {
                        echo '<option value="' . esc_attr($actionKey) . '" ' . selected($action, $actionKey, false) . '>' . esc_html($actionLabel) . '</option>';
                    }
                    echo '</select></label>';
                    echo '<label class="ak-condition-value" data-ak-condition-value ' . ($needsValue ? '' : 'hidden') . '>Érték<div class="ak-price-input"><input type="number" min="0" max="' . esc_attr($action === SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE ? '100' : (string) PHP_INT_MAX) . '" step="1" inputmode="numeric" name="' . esc_attr($name) . '[value]" value="' . esc_attr($value === null ? '' : (string) $value) . '" ' . ($needsValue ? '' : 'disabled') . ' data-ak-condition-value-input><span data-ak-condition-unit>' . esc_html($action === SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE ? '%' : 'Ft') . '</span></div></label>';
                    echo '<p class="ak-condition-inherited">' . esc_html($this->conditionSourceLabel($rule, $legacy, $question['question_key'], $option['answer_key'])) . '</p>';
                }
                echo '</div>';
                if ($question['question_key'] === 'service_history' && $option['answer_key'] !== 'none_known') {
                    $this->renderServiceHistoryComponentRules(
                        $book,
                        $model->modelKey,
                        $option,
                        $rule,
                        $legacy,
                        $componentMetadata['components'],
                        $rulesByCode,
                        $readOnly
                    );
                }
            }
            echo '</div></div>';
        }
        if ($lastPanel !== null) {
            echo '</div></section>';
        }
        if (! $readOnly) {
            echo '<div class="ak-condition-save ak-condition-save-bottom"><span data-ak-condition-changes aria-live="polite">Nincs mentetlen változás.</span><button type="submit" class="button button-primary">Módosítások mentése – ' . esc_html($model->label) . '</button></div></form>';
        }
        echo '</section>';
    }

    /** @param list<PricingRule> $rules */
    private function renderBatteryTab(PriceBook $book, array $rules, bool $readOnly, string $tab): void
    {
        try {
            $models = $this->catalog->iPhoneModels();
        } catch (DeviceCatalogUnavailableException $exception) {
            echo '<section class="ak-buyback-card"><h3>Akkumulátor</h3><div class="notice notice-error inline"><p>Az inventory készülékkatalógus nem érhető el, ezért az akkumulátorszabályok nem jeleníthetők meg.</p></div></section>';
            return;
        }
        $model = $this->selectedConditionModel($models);
        if ($model === null) {
            echo '<section class="ak-buyback-card"><h3>Akkumulátor</h3><div class="notice notice-error inline"><p>A megadott iPhone modell nem szerepel az inventory készülékkatalógusban.</p></div></section>';
            return;
        }
        $batteryQuestion = $this->questionnaire->questions()['battery_health'] ?? null;
        if (! is_array($batteryQuestion) || ! isset($batteryQuestion['min'], $batteryQuestion['max'])) {
            echo '<section class="ak-buyback-card"><h3>Akkumulátor</h3><div class="notice notice-error inline"><p>A nyilvános akkumulátor-kérdőív tartománya nem érhető el.</p></div></section>';
            return;
        }
        $minimum = (int) $batteryQuestion['min'];
        $maximum = (int) $batteryQuestion['max'];
        $bands = $this->modelBatteryBands($rules, $model->modelKey);
        $legacy = $this->legacyBatteryBands($rules);
        $summary = $this->batterySummary($bands, $minimum, $maximum);

        echo '<section class="ak-buyback-card ak-battery-editor"><h3>Az akkumulátor szabályai ehhez a modellhez</h3>';
        echo '<form method="get" class="ak-battery-model-selector" data-ak-battery-model-form><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '"><input type="hidden" name="book_id" value="' . esc_attr((string) $book->id()?->toInt()) . '"><input type="hidden" name="tab" value="' . esc_attr($tab) . '"><label for="ak-battery-model">Modell<select name="model" id="ak-battery-model" data-ak-battery-model-select data-ak-current-value="' . esc_attr($model->modelKey) . '">';
        foreach ($models as $item) {
            echo '<option value="' . esc_attr($item->modelKey) . '" ' . selected($model->modelKey, $item->modelKey, false) . '>' . esc_html($item->label) . '</option>';
        }
        echo '</select></label><button type="submit" class="button">Modell betöltése</button></form>';
        echo '<p>' . esc_html($readOnly ? 'Az akkumulátorszabályok itt csak olvashatók.' : 'Itt kizárólag a kiválasztott piszkozat és iPhone modell akkumulátorállapot-sávjai módosulnak. A százaléksávok között lehet hézag; ilyenkor a nem lefedett értékre nem alkalmazódik modellspecifikus akkumulátor-korrekció.') . '</p>';
        echo '<div class="ak-battery-summary"><strong>' . esc_html($model->label) . '</strong> · Beállított sávok: <strong data-ak-battery-configured>' . esc_html((string) $summary['configured']) . '</strong> · Kézi bevizsgálás: <strong data-ak-battery-manual>' . esc_html((string) $summary['manual']) . '</strong> · Nem vásároljuk fel: <strong data-ak-battery-reject>' . esc_html((string) $summary['reject']) . '</strong> · Mentetlen módosítások: <strong data-ak-battery-changes>0</strong>';
        echo '<br><strong>Lefedetlen tartomány:</strong> <span data-ak-battery-uncovered>' . esc_html($this->batteryRangeLabel($summary['uncovered'])) . '</span></div>';
        if ($bands === []) {
            echo '<div class="notice notice-info inline"><p>Ehhez a modellhez még nincs akkumulátorszabály beállítva.</p></div>';
        }
        if ($legacy !== []) {
            echo '<div class="notice notice-warning inline"><p><strong>Örökölt globális szabály</strong></p>';
            if ($bands === []) {
                echo '<p>Ehhez a modellhez nincs külön akkumulátorsáv. Az alábbi globális szabályok ezért erre a modellre is érvényesek.</p>';
            }
            echo '<ul>';
            foreach ($legacy as $rule) {
                echo '<li>' . esc_html($this->batteryRuleDescription($rule)) . '</li>';
            }
            echo '</ul><p>Ez a szerkesztő nem írja át az örökölt globális szabályokat. Modellspecifikus, egyező akkumulátorsáv esetén az engine a modellspecifikus szabályt használja.</p></div>';
        }
        if (! $readOnly) {
            echo '<form method="post" data-ak-battery-form data-ak-battery-min="' . esc_attr((string) $minimum) . '" data-ak-battery-max="' . esc_attr((string) $maximum) . '">';
            $this->securityFields('save_battery_bands', $book);
            $this->tabField($tab);
            echo '<input type="hidden" name="battery_model_key" value="' . esc_attr($model->modelKey) . '"><div class="ak-battery-save ak-battery-save-top"><span data-ak-battery-change-message aria-live="polite">Nincs mentetlen változás.</span><button type="submit" class="button button-primary">Akkumulátorszabályok mentése – ' . esc_html($model->label) . '</button></div>';
        }
        echo '<div class="ak-battery-bands" data-ak-battery-bands>';
        foreach ($bands as $index => $rule) {
            $this->renderBatteryBandRow($rule, $index, $minimum, $maximum, $readOnly);
        }
        echo '</div>';
        if (! $readOnly) {
            echo '<template data-ak-battery-row-template>';
            $this->renderBatteryBandRow(null, '__INDEX__', $minimum, $maximum, false);
            echo '</template>';
            echo '<p><button type="button" class="button" data-ak-battery-add>Új százaléksáv hozzáadása</button></p>';
            echo '<div class="ak-battery-save ak-battery-save-bottom"><span data-ak-battery-change-message aria-live="polite">Nincs mentetlen változás.</span><button type="submit" class="button button-primary">Akkumulátorszabályok mentése – ' . esc_html($model->label) . '</button></div></form>';
        }
        echo '</section>';
    }

    private function renderBatteryBandRow(?PricingRule $rule, int|string $index, int $minimum, int $maximum, bool $readOnly): void
    {
        $range = $rule === null ? null : $rule->definition()->comparisonValue;
        $bandMinimum = is_array($range) ? (int) $range[0] : $minimum;
        $bandMaximum = is_array($range) ? (int) $range[1] : $maximum;
        $action = $this->batteryAction($rule);
        $value = $this->batteryValue($rule, $action);
        $name = 'battery_bands[' . $index . ']';
        $original = implode('|', [$bandMinimum, $bandMaximum, $action, $value ?? '']);
        echo '<article class="ak-battery-band" data-ak-battery-row data-ak-battery-original="' . esc_attr($original) . '"' . ($rule !== null ? ' data-ak-battery-existing="1"' : '') . '><div class="ak-battery-band-heading"><strong>' . esc_html($rule === null ? 'Új százaléksáv' : 'Modellspecifikus szabály') . '</strong><span data-ak-battery-row-status>' . esc_html($rule === null ? 'Még nincs mentve' : 'Mentett sáv') . '</span></div>';
        if ($readOnly) {
            echo '<dl class="ak-battery-readonly"><dt>Százaléksáv</dt><dd>' . esc_html($bandMinimum . '–' . $bandMaximum . '%') . '</dd><dt>Következmény</dt><dd>' . esc_html($this->batteryActionLabel($action, $value)) . '</dd><dt>Forrás</dt><dd>Modellspecifikus szabály</dd></dl></article>';
            return;
        }
        echo '<input type="hidden" name="' . esc_attr($name) . '[rule_id]" value="' . esc_attr($rule?->id() === null ? '' : (string) $rule->id()->toInt()) . '"><input type="hidden" name="' . esc_attr($name) . '[delete]" value="" data-ak-battery-delete>';
        echo '<div class="ak-battery-band-fields"><label>Minimum (%)<input type="number" min="' . esc_attr((string) $minimum) . '" max="' . esc_attr((string) $maximum) . '" step="1" inputmode="numeric" name="' . esc_attr($name) . '[minimum]" value="' . esc_attr((string) $bandMinimum) . '" data-ak-battery-minimum required></label>';
        echo '<label>Maximum (%)<input type="number" min="' . esc_attr((string) $minimum) . '" max="' . esc_attr((string) $maximum) . '" step="1" inputmode="numeric" name="' . esc_attr($name) . '[maximum]" value="' . esc_attr((string) $bandMaximum) . '" data-ak-battery-maximum required></label>';
        echo '<label>Üzleti következmény<select name="' . esc_attr($name) . '[action]" data-ak-battery-action>';
        foreach ($this->batteryActions() as $actionKey => $actionLabel) {
            echo '<option value="' . esc_attr($actionKey) . '" ' . selected($action, $actionKey, false) . '>' . esc_html($actionLabel) . '</option>';
        }
        echo '</select></label>';
        $needsValue = in_array($action, [SaveDraftBatteryBandsHandler::ACTION_FIXED, SaveDraftBatteryBandsHandler::ACTION_PERCENTAGE], true);
        echo '<label data-ak-battery-value ' . ($needsValue ? '' : 'hidden') . '>Érték<div class="ak-price-input"><input type="number" min="0" max="' . esc_attr($action === SaveDraftBatteryBandsHandler::ACTION_PERCENTAGE ? '100' : (string) PHP_INT_MAX) . '" step="1" inputmode="numeric" name="' . esc_attr($name) . '[value]" value="' . esc_attr($value === null ? '' : (string) $value) . '" ' . ($needsValue ? '' : 'disabled') . ' data-ak-battery-value-input><span data-ak-battery-unit>' . esc_html($action === SaveDraftBatteryBandsHandler::ACTION_PERCENTAGE ? '%' : 'Ft') . '</span></div></label>';
        echo '</div><p class="description">A sáv mindkét határértéket tartalmazza. Meglévő sáv törléséhez a gomb külön megerősítést kér.</p><p><button type="button" class="button-link-delete" data-ak-battery-remove>Sáv törlése</button></p></article>';
    }

    /** @param list<PricingRule> $rules */
    private function renderOfferModesTab(PriceBook $book, array $rules, bool $readOnly, string $tab): void
    {
        $modeRules = [];
        $duplicates = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->kind->code() !== PricingRuleKind::MODE_ADJUSTMENT || ! in_array($definition->serviceMode, OfferModeDefinition::keys(), true)) {
                continue;
            }
            if (isset($modeRules[$definition->serviceMode])) {
                $duplicates[$definition->serviceMode] = true;
            }
            $modeRules[$definition->serviceMode] = $rule;
        }
        $configured = count($modeRules);
        echo '<section class="ak-buyback-card ak-offer-modes-editor"><h3>Ajánlattípusok</h3>';
        echo '<p>' . esc_html($readOnly ? 'Az ajánlattípusok neve és leírása minden árkönyvben azonos; a módosítók itt csak olvashatók.' : 'Az ajánlattípusok neve és leírása minden árkönyvben azonos. Itt csak az egész árkönyvre érvényes korrekciókat módosíthatod; modellhez és tárhelyhez nem kötődnek.') . '</p>';
        echo '<div class="ak-offer-mode-summary"><strong><span data-ak-offer-configured>' . esc_html((string) $configured) . '</span> / 4</strong> beállítva · <strong><span data-ak-offer-missing>' . esc_html((string) (4 - $configured)) . '</span></strong> nincs beállítva · <strong><span data-ak-offer-changes>0</span></strong> mentetlen módosítás</div>';
        if ($duplicates !== []) {
            echo '<div class="notice notice-error inline"><p>Egy vagy több ajánlattípushoz több szabály tartozik. A szerkesztés biztonsági okból nem elérhető, amíg ezt külön nem rendezik.</p></div>';
            $readOnly = true;
        }
        if (! $readOnly) {
            echo '<form method="post" data-ak-offer-mode-form>';
            $this->securityFields('save_offer_mode_modifiers', $book);
            $this->tabField($tab);
            echo '<div class="ak-offer-mode-save ak-offer-mode-save-top"><span data-ak-offer-change-message aria-live="polite">Nincs mentetlen változás.</span><button type="submit" class="button button-primary">Ajánlattípusok mentése</button></div>';
        }
        echo '<div class="ak-offer-mode-list">';
        foreach ($this->offerModes()->all() as $mode => $meta) {
            $this->renderOfferModeRow($mode, $meta, $modeRules[$mode] ?? null, $readOnly);
        }
        echo '</div>';
        if (! $readOnly) {
            echo '<div class="ak-offer-mode-save ak-offer-mode-save-bottom"><span data-ak-offer-change-message aria-live="polite">Nincs mentetlen változás.</span><button type="submit" class="button button-primary">Ajánlattípusok mentése</button></div></form>';
        }
        echo '</section>';
    }

    /** @param array{label:string,description:string,process:string} $meta */
    private function renderOfferModeRow(string $mode, array $meta, ?PricingRule $rule, bool $readOnly): void
    {
        $type = $rule?->definition()->amount !== null ? SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT : SaveDraftOfferModeModifiersHandler::TYPE_MULTIPLIER;
        $value = $type === SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT
            ? $rule?->definition()->amount?->amount()
            : ($rule?->definition()->multiplier === null ? null : $this->offerModePercentage($rule->definition()->multiplier->value()));
        $original = $rule === null ? 'missing' : 'configured|' . $type . '|' . ($value ?? '');
        $examples = $this->offerModeExamples->examples($mode, $rule?->definition());
        echo '<article class="ak-offer-mode-row" data-ak-offer-mode-row data-ak-offer-original="' . esc_attr($original) . '"><div class="ak-offer-mode-copy"><h4>' . esc_html($meta['label']) . '</h4><p>' . esc_html($meta['description']) . '</p><p class="description">' . esc_html($meta['process']) . '</p><p class="ak-offer-mode-examples"><strong>Példa korrigált készülékértékre:</strong> 50 000 Ft → ' . esc_html(number_format_i18n($examples[50000])) . ' Ft · 300 000 Ft → ' . esc_html(number_format_i18n($examples[300000])) . ' Ft</p></div>';
        if ($readOnly) {
            echo '<div class="ak-offer-mode-current"><strong>' . esc_html($rule === null ? 'Nincs külön módosító (0)' : $this->offerModeValueLabel($rule)) . '</strong><p>' . esc_html($rule === null ? 'Nincs beállítva' : 'Árkönyvszintű szabály') . '</p></div></article>';
            return;
        }
        echo '<input type="hidden" name="offer_mode_modifiers[' . esc_attr($mode) . '][mode]" value="' . esc_attr($mode) . '">';
        echo '<label class="ak-offer-mode-type">Korrekció típusa<select name="offer_mode_modifiers[' . esc_attr($mode) . '][type]" data-ak-offer-type><option value="amount" ' . selected($type, SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT, false) . '>Fix összeg</option><option value="multiplier" ' . selected($type, SaveDraftOfferModeModifiersHandler::TYPE_MULTIPLIER, false) . '>Százalékos</option></select></label>';
        echo '<label class="ak-offer-mode-value">Érték<div class="ak-price-input"><input type="number" min="' . esc_attr($type === SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT ? (string) -PHP_INT_MAX : '-100') . '" max="' . esc_attr($type === SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT ? (string) PHP_INT_MAX : '400') . '" step="' . esc_attr($type === SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT ? '1' : '0.01') . '" inputmode="decimal" name="offer_mode_modifiers[' . esc_attr($mode) . '][value]" value="' . esc_attr($value === null ? '' : (string) $value) . '" data-ak-offer-value><span data-ak-offer-unit>' . esc_html($type === SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT ? 'Ft' : '%') . '</span></div><small data-ak-offer-help>' . esc_html($type === SaveDraftOfferModeModifiersHandler::TYPE_AMOUNT ? 'Előjeles egész Ft: mínusz csökkent, plusz növel.' : 'Előjeles százalék: -100%–+400%, legfeljebb két tizedessel.') . '</small></label>';
        echo '<label class="ak-offer-mode-remove"><input type="checkbox" name="offer_mode_modifiers[' . esc_attr($mode) . '][remove]" value="1" data-ak-offer-remove> Nincs módosítás (0)</label>';
        echo '</article>';
    }

    private function offerModeValueLabel(PricingRule $rule): string
    {
        $definition = $rule->definition();
        return $definition->amount !== null
            ? number_format_i18n($definition->amount->amount()) . ' Ft fix korrekció'
            : $this->offerModePercentage($definition->multiplier?->value() ?? 0) . '% százalékos korrekció';
    }

    private function offerModePercentage(int $basisPoints): string
    {
        return $this->basisPointsPercent($basisPoints - \AppleKlinika\Buyback\Domain\Pricing\BasisPointsMultiplier::ONE);
    }

    private function renderUnavailableEditor(string $title, string $message): void
    {
        echo '<section class="ak-buyback-card"><h3>' . esc_html($title) . ' <span class="ak-development-badge">Fejlesztés alatt</span></h3><div class="notice notice-info inline"><p>' . esc_html($message) . '</p></div></section>';
    }

    private function renderPreviewPlaceholder(): void
    {
        echo '<section class="ak-buyback-card"><h3>Tesztkalkulátor <span class="ak-development-badge">Fejlesztés alatt</span></h3><div class="notice notice-info inline"><p>A felhasználóbarát piszkozat-tesztkalkulátor még nem készült el.</p><p>A későbbi kalkulátor a nyilvános kérdőívet követi, a kiválasztott piszkozatot használja, részletes árbontást mutat, és nem hoz létre ügyfélkérelmet.</p></div></section>';
    }

    /** @param list<PricingRule> $rules */
    private function renderReadOnlyTabPlaceholder(string $tab, PriceBook $book, array $rules): void
    {
        if ($tab === self::TAB_CONDITIONS) {
            $this->renderConditionsTab($book, $rules, true, $tab);
            return;
        }
        if ($tab === self::TAB_BATTERY) {
            $this->renderBatteryTab($book, $rules, true, $tab);
            return;
        }
        if ($tab === self::TAB_OFFER_MODES) {
            $this->renderOfferModesTab($book, $rules, true, $tab);
            return;
        }
        if ($tab === self::TAB_PREVIEW) {
            $this->renderCalculationPreview($book, $tab);
            return;
        }
    }

    /** @param list<PricingRule> $rules */
    private function renderActivationReadiness(PriceBook $book, array $rules): void
    {
        $report = $this->readiness->evaluate($book, $rules, $this->clock->now());
        echo '<section class="ak-buyback-card ak-activation-readiness"><h3>Aktiválási ellenőrzés</h3>';
        echo '<p><span class="ak-status">' . esc_html($report->ready ? 'Aktiválásra kész' : 'Nem aktiválható') . '</span></p>';
        echo '<dl class="ak-readiness-summary"><dt>Aktív alapárak</dt><dd>' . esc_html((string) $report->enabledBasePriceCount) . '</dd><dt>Támogatott modell/tárhely párok</dt><dd>' . esc_html((string) $report->supportedConfigurationCount()) . '</dd><dt>Aktív korrekciók</dt><dd>' . esc_html((string) $report->enabledAdjustmentCount) . '</dd><dt>Árkönyvverzió</dt><dd>v' . esc_html((string) $report->versionNumber->value()) . '</dd><dt>Aggregate verzió</dt><dd>' . esc_html((string) $book->version()->value()) . '</dd></dl>';
        echo '<p><strong>Átvételi módok:</strong> ' . esc_html(implode(', ', array_map([$this, 'serviceModeLabel'], $report->supportedServiceModes))) . '</p>';
        if ($report->blockingIssues !== []) {
            echo '<h4>Blokkoló hibák</h4><ul class="ak-readiness-issues">';
            foreach ($report->blockingIssues as $issue) {
                echo '<li><code>' . esc_html($issue) . '</code> – ' . esc_html($this->readinessMessage($issue)) . '</li>';
            }
            echo '</ul>';
        }
        if ($report->warnings !== []) {
            echo '<h4>Figyelmeztetések</h4><ul class="ak-readiness-warnings">';
            foreach ($report->warnings as $warning) {
                echo '<li><code>' . esc_html($warning) . '</code> – ' . esc_html($this->readinessMessage($warning)) . '</li>';
            }
            echo '</ul>';
        }
        if ($report->ready && current_user_can(CapabilityManager::ACTIVATE_PRICE_BOOKS)) {
            echo '<div class="notice notice-warning inline"><p>Az aktiválás után az árkönyv és szabályai nem szerkeszthetők. Ha már van aktív HUF árkönyv, az automatikusan archiválásra kerül.</p></div>';
            echo '<form method="post" class="ak-activation-form">';
            $this->securityFields('activate_price_book', $book);
            $this->textField('activation_confirmation', 'Megerősítés: írd be pontosan ezt a nevet: ' . $book->label(), '', true);
            submit_button('Árkönyv aktiválása', 'primary');
            echo '</form>';
        } else {
            echo '<p><strong>Az aktiválás nem érhető el, amíg minden blokkoló hiba meg nem szűnik.</strong></p>';
        }
        echo '</section>';
    }

    private function renderCalculationPreview(PriceBook $book, string $tab): void
    {
        $preview = null;
        $error = null;
        $posted = isset($_POST['ak_buyback_action']) && sanitize_key((string) wp_unslash($_POST['ak_buyback_action'])) === 'preview_calculation';
        $post = $posted ? wp_unslash($_POST) : [];
        $state = isset($post['preview_questionnaire']) && is_array($post['preview_questionnaire'])
            ? $this->questionnaire->sanitize($post['preview_questionnaire'])
            : $this->questionnaire->defaults();

        if ($posted) {
            try {
                $nonce = sanitize_text_field((string) ($post['_ak_buyback_nonce'] ?? ''));
                $this->authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, $nonce);
                $query = $this->previewParser->parse($post);
                if ($query->priceBookId !== $book->id()?->toInt()) {
                    throw new \InvalidArgumentException('Az előnézet árkönyve nem egyezik a megnyitott árkönyvvel.');
                }
                $preview = $this->previewHandler->handle($query);
                $state = $preview->questionnaireState;
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        echo '<section class="ak-buyback-card ak-pricing-preview" id="test-calculator"><h3>Tesztkalkulátor</h3>';
        echo '<p><strong>Árkönyv:</strong> ' . esc_html($book->label()) . ' · <span class="ak-status">' . esc_html($this->statusLabel($book)) . '</span></p>';
        if ($book->status()->isDraft()) {
            echo '<div class="notice notice-warning inline"><p>Ez a kalkuláció a kiválasztott piszkozat szabályait használja. A nyilvános /eladas/ oldal és az aktív árkönyv változatlan marad.</p></div>';
        }
        echo '<div class="notice notice-info inline"><p>A tesztkalkuláció nem hoz létre ügyfélkérelmet, ajánlatot vagy rendelést.</p></div>';
        if ($error !== null) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($error) . '</p></div>';
        }

        $catalog = [];
        $configurations = [];
        try {
            $catalog = $this->catalog->iPhoneCatalog();
            foreach ($this->catalog->iPhoneConfigurations() as $configuration) {
                $configurations[$configuration->modelKey][] = $configuration->storageGb;
            }
        } catch (DeviceCatalogUnavailableException $exception) {
            $error ??= 'Az inventory készülékkatalógus nem érhető el.';
        }

        echo '<div class="ak-preview-layout"><form method="post" class="ak-preview-form" data-ak-preview-form data-ak-preview-catalog="' . esc_attr(wp_json_encode(['catalog' => $catalog, 'configurations' => $configurations])) . '">';
        $this->securityFields('preview_calculation', $book);
        $this->tabField($tab);
        echo '<div class="ak-preview-device">';
        $selectedModel = sanitize_key((string) ($post['preview_model_key'] ?? ''));
        $selectedStorage = (string) ($post['preview_storage_gb'] ?? '');
        $selectedColor = sanitize_key((string) ($post['preview_color_key'] ?? ''));
        echo '<p><label for="preview_model_key">iPhone modell</label><select name="preview_model_key" id="preview_model_key" data-ak-preview-model required><option value="">Válassz modellt</option>';
        foreach ($catalog as $modelKey => $item) {
            echo '<option value="' . esc_attr($modelKey) . '" ' . selected($selectedModel, $modelKey, false) . '>' . esc_html($item['label']) . '</option>';
        }
        echo '</select></p><p><label for="preview_storage_gb">Tárhely</label><select name="preview_storage_gb" id="preview_storage_gb" data-ak-preview-storage required><option value="">Válassz tárhelyet</option>';
        foreach ($configurations[$selectedModel] ?? [] as $storage) {
            echo '<option value="' . esc_attr((string) $storage) . '" ' . selected((string) ($post['preview_storage_gb'] ?? ''), (string) $storage, false) . '>' . esc_html((string) $storage) . ' GB</option>';
        }
        echo '</select></p><p data-ak-preview-color-wrap' . (($catalog[$selectedModel]['colors'] ?? []) === [] ? ' hidden' : '') . '><label for="preview_color_key">Szín</label><select name="preview_color_key" id="preview_color_key" data-ak-preview-color><option value="">Nincs kiválasztva</option>';
        foreach (($catalog[$selectedModel]['colors'] ?? []) as $colorKey => $colorLabel) {
            echo '<option value="' . esc_attr($colorKey) . '" ' . selected($selectedColor, $colorKey, false) . '>' . esc_html($colorLabel) . '</option>';
        }
        echo '</select></p></div><div class="ak-preview-questionnaire">';
        foreach ($this->questionnaire->panelOrder() as $panel) {
            if (in_array($panel, ['model', 'offers', 'review'], true)) {
                continue;
            }
            echo '<fieldset class="ak-preview-panel"><legend>' . esc_html($this->questionnaire->panel($panel)['title']) . '</legend>';
            foreach ($this->questionnaire->questionsForPanel($panel) as $key => $question) {
                $this->renderPreviewQuestion($key, $question, $state);
            }
            echo '</fieldset>';
        }
        echo '</div>';
        echo '<p><button type="submit" class="button button-primary">Tesztkalkuláció futtatása</button> <button type="reset" class="button" data-ak-preview-reset>Űrlap alaphelyzetbe</button></p>';
        echo '</form>';

        echo '<aside class="ak-preview-results" data-ak-preview-results>';
        if ($preview !== null) {
            $modelLabel = $catalog[$preview->modelKey]['label'] ?? $preview->modelKey;
            $colorLabel = $catalog[$preview->modelKey]['colors'][$preview->colorKey] ?? '';
            echo '<header class="ak-preview-result-header"><h3>Eredmény</h3><p><strong>' . esc_html($modelLabel) . '</strong> · ' . esc_html($this->storageLabel($preview->storageGb)) . ($colorLabel !== '' ? ' · ' . esc_html($colorLabel) : '') . '</p><p class="description">' . esc_html($book->label()) . ' · ' . esc_html($this->statusLabel($book)) . '</p></header>';
            $reference = $preview->modeResults[\AppleKlinika\Buyback\Domain\Buyback\ServiceMode::HIGHER_OFFER] ?? reset($preview->modeResults);
            $ruleDetails = $this->previewRuleDetails($this->rules->listForPriceBook($book->id()));
            if ($reference->outcome->code() === PricingOutcome::OFFERED) {
                echo '<dl class="ak-preview-summary"><dt>Alapár</dt><dd>' . esc_html(number_format_i18n($reference->baseAmount?->amount() ?? 0)) . ' Ft</dd><dt>Korrekciózott készülékérték</dt><dd>' . esc_html(number_format_i18n($reference->amountAfterConditionMultipliers?->amount() ?? 0)) . ' Ft</dd></dl>';
                $this->renderSharedPreviewBreakdown($reference, $ruleDetails);
                $suppressed = $this->previewSuppressedFallbacks($preview, $this->rules->listForPriceBook($book->id()), $ruleDetails);
                if ($suppressed !== []) {
                    echo '<details class="ak-preview-suppressed"><summary>Elnyomott örökölt globális szabályok</summary><ul>';
                    foreach ($suppressed as $item) { echo '<li>' . esc_html($item) . '</li>'; }
                    echo '</ul></details>';
                }
                echo '<div class="ak-preview-offer-grid">';
                foreach ($preview->modeResults as $mode => $result) {
                    $this->renderPreviewResult($mode, $result, $ruleDetails);
                }
                echo '</div>';
            } else {
                $this->renderPreviewOutcome($reference);
            }
        } else {
            echo '<p class="description">Töltsd ki a nyilvános kérdőívvel azonos mezőket, majd futtasd a nem perzisztáló tesztkalkulációt.</p>';
        }
        echo '</aside></div></section>';
    }

    /** @param array<string,mixed> $question @param array<string,mixed> $state */
    private function renderPreviewQuestion(string $key, array $question, array $state): void
    {
        $conditional = isset($question['conditional_on']) ? ' data-ak-preview-conditional="' . esc_attr((string) $question['conditional_on']) . '" data-ak-preview-except="' . esc_attr((string) ($question['conditional_except'] ?? '')) . '"' : '';
        echo '<div class="ak-preview-question"' . $conditional . '><label>' . esc_html((string) $question['label']) . '</label>';
        if (($question['helper'] ?? '') !== '') {
            echo '<p class="description">' . esc_html((string) $question['helper']) . '</p>';
        }
        $value = $state[$key] ?? $question['default'];
        if ($question['type'] === 'range') {
            echo '<input type="number" name="preview_questionnaire[' . esc_attr($key) . ']" min="' . esc_attr((string) $question['min']) . '" max="' . esc_attr((string) $question['max']) . '" step="1" value="' . esc_attr((string) $value) . '" required> %';
        } elseif ($question['type'] === 'multi') {
            foreach ($question['options'] as $optionKey => $option) {
                echo '<label class="ak-preview-check"><input type="checkbox" name="preview_questionnaire[' . esc_attr($key) . '][]" value="' . esc_attr((string) $optionKey) . '" ' . checked(in_array((string) $optionKey, (array) $value, true), true, false) . '> ' . esc_html((string) $option['label']) . '</label>';
            }
        } else {
            echo '<select name="preview_questionnaire[' . esc_attr($key) . ']" data-ak-preview-question="' . esc_attr($key) . '" required>';
            foreach ($question['options'] as $optionKey => $option) {
                echo '<option value="' . esc_attr((string) $optionKey) . '" ' . selected((string) $value, (string) $optionKey, false) . '>' . esc_html((string) $option['label']) . '</option>';
            }
            echo '</select>';
        }
        echo '</div>';
    }

    /** @param array<string,array{label:string,source:string}> $ruleDetails */
    private function renderPreviewResult(string $mode, PricingCalculationResult $result, array $ruleDetails = []): void
    {
        echo '<article class="ak-preview-result"><h4>' . esc_html($this->serviceModeLabel($mode)) . '</h4>';
        $modeLine = null;
        $minimumLine = null;
        $roundingLine = null;
        foreach ($result->breakdown as $line) {
            if (in_array($line->type, ['mode_fixed_adjustment', 'mode_multiplier'], true)) {
                $modeLine = $line;
            } elseif ($line->type === 'minimum_policy') {
                $minimumLine = $line;
            } elseif ($line->type === 'rounding') {
                $roundingLine = $line;
            }
        }
        echo '<p class="ak-preview-mode-detail"><strong>Módosító:</strong> ' . esc_html($modeLine === null ? 'Nincs módosítás' : $this->previewModeModifier($modeLine)) . '</p>';
        echo '<p class="ak-preview-mode-detail">Nyers összeg: ' . esc_html(number_format_i18n($result->rawAmountBeforeMinimumAndRounding?->amount() ?? 0)) . ' Ft</p>';
        if ($minimumLine !== null) {
            echo '<p class="ak-preview-mode-detail">Minimumár-kezelés: ' . esc_html($this->previewLineDetail($minimumLine, $ruleDetails)['label']) . '</p>';
        }
        if ($roundingLine !== null) {
            echo '<p class="ak-preview-mode-detail">Kerekítés: ' . esc_html($this->previewLineChange($roundingLine)) . '</p>';
        }
        echo '<p class="ak-preview-amount">' . esc_html(number_format_i18n($result->finalAmount?->amount() ?? 0)) . ' Ft</p>';
        echo '</article>';
    }

    /** @param array<string,array{label:string,source:string}> $ruleDetails */
    private function renderSharedPreviewBreakdown(PricingCalculationResult $result, array $ruleDetails): void
    {
        echo '<section class="ak-preview-breakdown"><h4>Árbontás</h4><ol class="ak-preview-breakdown-list">';
        foreach ($result->breakdown as $line) {
            if (! in_array($line->type, ['base_price', 'fixed_deduction', 'multiplier'], true)) {
                continue;
            }
            $detail = $this->previewLineDetail($line, $ruleDetails);
            echo '<li class="ak-preview-breakdown-row"><div><strong>' . esc_html($this->previewSharedLineLabel($line, $detail)) . '</strong><span>' . esc_html($detail['label']) . '</span><small>' . esc_html($detail['source']) . '</small></div><b>' . esc_html($this->previewLineChange($line)) . '</b></li>';
        }
        echo '</ol><p class="ak-preview-corrected"><strong>Korrigált készülékérték</strong><b>' . esc_html(number_format_i18n($result->amountAfterConditionMultipliers?->amount() ?? 0)) . ' Ft</b></p></section>';
    }

    /** @param array<string,array{label:string,source:string}> $ruleDetails @return array{label:string,source:string} */
    private function previewLineDetail(object $line, array $ruleDetails): array
    {
        return $line->ruleCode === null
            ? ['label' => 'Rendszerszabály', 'source' => 'Rendszerszabály']
            : ($ruleDetails[$line->ruleCode] ?? ['label' => ($line->publicLabel ?? 'Árkönyvszabály'), 'source' => 'Árkönyvszabály']);
    }

    private function previewLineChange(object $line): string
    {
        if ($line->multiplierBps !== null) {
            return $this->basisPointsPercent($line->multiplierBps) . '%';
        }
        if ($line->adjustmentAmountMinor !== null) {
            $amount = $line->adjustmentAmountMinor;
            return ($amount > 0 ? '+' : '') . number_format_i18n($amount) . ' Ft';
        }
        return '–';
    }

    private function previewModeModifier(object $line): string
    {
        if ($line->type === 'mode_multiplier' && $line->multiplierBps !== null) {
            $percent = ($line->multiplierBps / 100) - 100;
            return ($percent > 0 ? '+' : '') . number_format_i18n($percent, 0) . '%';
        }
        return $this->previewLineChange($line);
    }

    /** @param array{label:string,source:string} $detail */
    private function previewSharedLineLabel(object $line, array $detail): string
    {
        if ($line->type === 'base_price') {
            return 'Alapár';
        }
        if (str_starts_with($detail['label'], 'Akkumulátor')) {
            return 'Akkumulátor';
        }
        return $line->type === 'multiplier' ? 'Állapotkorrekció' : 'Állapotlevonás';
    }

    private function renderPreviewOutcome(PricingCalculationResult $result): void
    {
        $code = $result->outcome->code();
        $title = match ($code) {
            PricingOutcome::MANUAL_REVIEW => 'Kézi bevizsgálás szükséges',
            PricingOutcome::REJECTED => 'A készülék jelen állapotban nem vásárolható fel',
            PricingOutcome::CONFIGURATION_ERROR => 'Ehhez a modell- és tárhely-konfigurációhoz nincs alapár beállítva ebben az árkönyvben.',
            default => $this->outcomeLabel($code),
        };
        echo '<article class="ak-preview-outcome ak-preview-outcome-' . esc_attr($code) . '"><h4>' . esc_html($title) . '</h4>';
        if ($result->reasonCodes !== []) {
            echo '<ul class="ak-preview-reasons">';
            foreach ($result->reasonCodes as $reason) {
                echo '<li>' . esc_html($this->previewReasonLabel($reason)) . '</li>';
            }
            echo '</ul>';
        }
        echo '</article>';
    }

    private function previewReasonLabel(string $reason): string
    {
        return match ($reason) {
            'below_minimum_offer' => 'Az összeg az árkönyvben beállított minimum alatt van.',
            'below_model_minimum_offer' => 'Az összeg elérte a modellhez beállított automatikus ajánlati minimumot.',
            'missing_base_price' => 'Ehhez a modell- és tárhely-konfigurációhoz nincs alapár.',
            default => str_starts_with($reason, 'A ') ? $reason : 'A kiválasztott állapothoz kézi ellenőrzés szükséges.',
        };
    }

    /** @param list<PricingRule> $rules @return array<string,array{label:string,source:string}> */
    private function previewRuleDetails(array $rules): array
    {
        $details = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            $label = $definition->serviceMode !== null
                ? $this->serviceModeLabel($definition->serviceMode)
                : ($definition->publicLabel ?? '');
            if ($label === '' && $definition->conditionKey === 'battery_health') {
                $label = 'Akkumulátor ' . $this->comparisonLabel($definition->comparisonValue);
            }
            if ($label === '') {
                foreach ($this->questionnaire->conditionEditorQuestions() as $question) {
                    foreach ($question['options'] as $option) {
                        if (($option['condition_key'] ?? null) === $definition->conditionKey && ($option['comparison_value'] ?? null) === $definition->comparisonValue) {
                            $label = $question['label'] . ': ' . $option['label'];
                            break 2;
                        }
                    }
                }
            }
            $details[$definition->code->code()] = [
                'label' => $label !== '' ? $label : ($definition->serviceMode !== null ? $this->serviceModeLabel($definition->serviceMode) : 'Árkönyvszabály'),
                'source' => $definition->modelKey !== null ? 'Modellspecifikus szabály' : 'Örökölt globális szabály',
            ];
        }
        return $details;
    }

    private function comparisonLabel(mixed $comparison): string
    {
        return is_array($comparison) ? ((string) $comparison[0] . '–' . (string) $comparison[1] . '%') : ((string) $comparison . '%');
    }

    /** @param list<PricingRule> $rules @param array<string,array{label:string,source:string}> $details @return list<string> */
    private function previewSuppressedFallbacks(\AppleKlinika\Buyback\Application\Pricing\DraftPriceBookPreview $preview, array $rules, array $details): array
    {
        $answers = \AppleKlinika\Buyback\Domain\Pricing\ConditionAnswerCollection::fromAssociative($this->questionnaire->mapToConditions($preview->questionnaireState));
        $matcher = new \AppleKlinika\Buyback\Domain\Pricing\ConditionMatcher();
        $modelMatches = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->modelKey === $preview->modelKey && $definition->conditionKey !== null && $matcher->matches($definition, $answers)) {
                $modelMatches[] = $rule;
            }
        }
        $suppressed = [];
        foreach ($rules as $rule) {
            $definition = $rule->definition();
            if ($definition->modelKey !== null || $definition->conditionKey === null || ! $matcher->matches($definition, $answers)) {
                continue;
            }
            foreach ($modelMatches as $modelRule) {
                $modelDefinition = $modelRule->definition();
                $sameBattery = $definition->conditionKey === 'battery_health' && $modelDefinition->conditionKey === 'battery_health';
                $sameTarget = $definition->conditionKey === $modelDefinition->conditionKey && $definition->operator?->code() === $modelDefinition->operator?->code() && $definition->comparisonValue === $modelDefinition->comparisonValue;
                if ($sameBattery || $sameTarget) {
                    $suppressed[] = ($details[$definition->code->code()]['label'] ?? 'Örökölt globális szabály') . ' – modellspecifikus szabály felülírta.';
                    break;
                }
            }
        }
        return array_values(array_unique($suppressed));
    }

    /** @param list<PricingRule> $rules */
    private function renderRulesTable(PriceBook $book, array $rules, string $heading = 'Árazási szabályok', ?callable $filter = null): void
    {
        $visibleRules = $filter === null ? $rules : array_values(array_filter($rules, $filter));
        echo '<section class="ak-buyback-card ak-buyback-rules"><h3>' . esc_html($heading) . '</h3><table class="widefat striped"><thead><tr><th>Prioritás</th><th>Kód</th><th>Típus</th><th>Cél</th><th>Érték</th><th>Állapot</th><th>Művelet</th></tr></thead><tbody>';
        foreach ($visibleRules as $rule) {
            $definition = $rule->definition();
            echo '<tr><td>' . esc_html((string) $definition->priority->value()) . '</td><td><code>' . esc_html($definition->code->code()) . '</code></td><td>' . esc_html($this->kindLabel($definition->kind->code())) . '</td><td>' . esc_html($definition->modelKey ?? $definition->conditionKey ?? $definition->serviceMode ?? '–') . '</td><td>' . esc_html($this->ruleValue($rule)) . '</td><td>' . esc_html($definition->enabled ? 'Engedélyezve' : 'Kikapcsolva') . '</td><td>';
            if ($book->status()->isDraft()) {
                echo '<a class="button" href="' . esc_url(add_query_arg(['page' => self::SLUG, 'book_id' => $book->id()->toInt(), 'rule_id' => $rule->id()->toInt()], admin_url('admin.php'))) . '">Szerkesztés</a> ';
                $this->inlineRuleAction($book, $rule, 'toggle_rule', $definition->enabled ? 'Kikapcsolás' : 'Bekapcsolás', $definition->enabled ? '0' : '1');
                $this->inlineRuleAction($book, $rule, 'delete_rule', 'Törlés');
            } else {
                echo 'Csak olvasható';
            }
            echo '</td></tr>';
        }
        if ($visibleRules === []) {
            echo '<tr><td colspan="7">Ehhez a piszkozathoz még nincs szabály.</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    /** @param list<PricingRule> $rules */
    private function basePriceCount(array $rules): int
    {
        return count(array_filter($rules, static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::BASE_PRICE));
    }

    private function basePriceKey(string $modelKey, int $storageGb): string { return $modelKey . ':' . $storageGb; }
    private function storageLabel(int $storageGb): string { return $storageGb % 1024 === 0 ? ($storageGb / 1024) . ' TB' : $storageGb . ' GB'; }

    private function renderRuleForm(PriceBook $book, ?PricingRule $rule, string $defaultKind, string $tab): void
    {
        $definition = $rule?->definition();
        echo '<form method="post" class="ak-rule-form">';
        $this->securityFields($rule === null ? 'add_rule' : 'update_rule', $book, $rule);
        $this->tabField($tab);
        $this->textField('rule_code', 'Szabálykód', $definition?->code->code() ?? '', true);
        echo '<p><label for="rule_kind">Szabálytípus</label><select name="rule_kind" id="rule_kind" data-ak-rule-kind>';
        foreach ([PricingRuleKind::BASE_PRICE, PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER, PricingRuleKind::MODE_ADJUSTMENT, PricingRuleKind::HARD_REJECT, PricingRuleKind::MANUAL_REVIEW] as $kind) {
            echo '<option value="' . esc_attr($kind) . '" ' . selected($definition?->kind->code() ?? $defaultKind, $kind, false) . '>' . esc_html($this->kindLabel($kind)) . '</option>';
        }
        echo '</select></p>';
        $this->numberField('priority', 'Prioritás', $definition?->priority->value() ?? 100, -100000);
        echo '<p><label><input type="checkbox" name="is_enabled" value="1" ' . checked($definition?->enabled ?? true, true, false) . '> Engedélyezve</label></p>';

        echo '<div class="ak-rule-field" data-kinds="base_price"><p><label for="model_key">iPhone modell</label><select name="model_key" id="model_key">';
        try {
            foreach ($this->catalog->iPhoneModels() as $item) {
                echo '<option value="' . esc_attr($item->modelKey) . '" ' . selected($definition?->modelKey, $item->modelKey, false) . '>' . esc_html($item->label) . '</option>';
            }
        } catch (DeviceCatalogUnavailableException $exception) {
            echo '<option value="">A készülékkatalógus nem érhető el</option>';
        }
        echo '</select></p><p><label for="storage_gb">Tárhely</label><select name="storage_gb" id="storage_gb">';
        foreach (self::STORAGE_OPTIONS as $storage) {
            echo '<option value="' . esc_attr((string) $storage) . '" ' . selected($definition?->storage?->gigabytes(), $storage, false) . '>' . esc_html((string) $storage) . ' GB</option>';
        }
        echo '</select></p></div>';

        echo '<div class="ak-rule-field" data-kinds="fixed_deduction multiplier hard_reject manual_review">';
        echo '<p><label for="condition_key">Feltétel</label><select name="condition_key" id="condition_key">';
        foreach (ConditionDefinition::keys() as $key) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($definition?->conditionKey, $key, false) . '>' . esc_html(ConditionDefinition::labelFor($key)) . ' (' . esc_html($key) . ')</option>';
        }
        echo '</select></p><p><label for="comparison_operator">Operátor</label><select name="comparison_operator" id="comparison_operator">';
        foreach (ComparisonOperator::supported() as $operator) {
            echo '<option value="' . esc_attr($operator) . '" ' . selected($definition?->operator?->code(), $operator, false) . '>' . esc_html($operator) . '</option>';
        }
        $comparisonValue = $definition === null ? '' : $this->comparisonDisplay($definition->comparisonValue);
        echo '</select></p>';
        $this->textField('comparison_value', 'Összehasonlítás értéke', $comparisonValue, true);
        echo '</div>';

        echo '<div class="ak-rule-field" data-kinds="mode_adjustment"><p><label for="service_mode">Átvételi mód</label><select name="service_mode" id="service_mode">';
        foreach (['in_store_instant', 'fast_online', 'higher_offer', 'trade_in'] as $mode) {
            echo '<option value="' . esc_attr($mode) . '" ' . selected($definition?->serviceMode, $mode, false) . '>' . esc_html($mode) . '</option>';
        }
        echo '</select></p><p><label for="adjustment_type">Korrekció típusa</label><select name="adjustment_type" id="adjustment_type"><option value="amount" ' . selected($definition?->amount !== null, true, false) . '>Fix összeg</option><option value="multiplier" ' . selected($definition?->multiplier !== null, true, false) . '>Szorzó</option></select></p></div>';
        echo '<div class="ak-rule-field" data-kinds="base_price fixed_deduction mode_adjustment" data-adjustment-type="amount">';
        $this->numberField('amount_minor', 'Összeg (Ft)', $definition?->amount?->amount() ?? null, 0, false);
        echo '</div><div class="ak-rule-field" data-kinds="multiplier mode_adjustment" data-adjustment-type="multiplier">';
        $this->textField('multiplier_percent', 'Szorzó (%)', $definition?->multiplier === null ? '' : $this->basisPointsPercent($definition->multiplier->value()), false);
        echo '</div>';
        $this->textField('public_label', 'Nyilvános címke', $definition?->publicLabel ?? '', false);
        echo '<p><label for="internal_note">Belső megjegyzés</label><textarea name="internal_note" id="internal_note" rows="3">' . esc_textarea($definition?->internalNote ?? '') . '</textarea></p>';
        submit_button($rule === null ? 'Szabály hozzáadása' : 'Szabály mentése');
        echo '</form>';
    }

    private function securityFields(string $action, ?PriceBook $book = null, ?PricingRule $rule = null): void
    {
        wp_nonce_field(AdminAuthorization::NONCE_ACTION, '_ak_buyback_nonce');
        echo '<input type="hidden" name="ak_buyback_action" value="' . esc_attr($action) . '">';
        if ($book !== null) {
            echo '<input type="hidden" name="price_book_id" value="' . esc_attr((string) $book->id()->toInt()) . '"><input type="hidden" name="expected_book_version" value="' . esc_attr((string) $book->version()->value()) . '">';
        }
        if ($rule !== null) {
            echo '<input type="hidden" name="rule_id" value="' . esc_attr((string) $rule->id()->toInt()) . '"><input type="hidden" name="expected_rule_version" value="' . esc_attr((string) $rule->version()->value()) . '">';
        }
    }

    private function inlineRuleAction(PriceBook $book, PricingRule $rule, string $action, string $label, ?string $enabled = null): void
    {
        echo '<form method="post" class="ak-inline-form">';
        $this->securityFields($action, $book, $rule);
        if ($enabled !== null) {
            echo '<input type="hidden" name="enabled" value="' . esc_attr($enabled) . '">';
        }
        echo '<button type="submit" class="button' . ($action === 'delete_rule' ? ' button-link-delete' : '') . '">' . esc_html($label) . '</button></form> ';
    }

    private function renderTabs(int $bookId, string $tab): void
    {
        echo '<nav class="nav-tab-wrapper">';
        if (current_user_can(CapabilityManager::VIEW_DIAGNOSTICS)) {
            echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=' . DiagnosticsPage::SLUG)) . '">Diagnosztika</a>';
        }
        echo '<a class="nav-tab' . ($bookId === 0 ? ' nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Árkönyvek</a>';
        if ($bookId > 0) {
            foreach ([self::TAB_BASE_PRICES => 'Alapárak', self::TAB_CONDITIONS => 'Állapotlevonások', self::TAB_BATTERY => 'Akkumulátor', self::TAB_OFFER_MODES => 'Ajánlattípusok', self::TAB_PREVIEW => 'Tesztkalkulátor'] as $value => $label) {
                $status = '';
                echo '<a class="nav-tab' . ($tab === $value ? ' nav-tab-active' : '') . '" href="' . esc_url($this->tabUrl($bookId, $value)) . '">' . esc_html($label) . $status . '</a>';
            }
        }
        echo '</nav>';
    }

    private function renderActiveBookNotice(): void
    {
        try {
            $resolved = $this->activePriceBookResolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $this->clock->now());
        echo '<div class="notice notice-success inline"><p><strong>Jelenleg használt HUF árkönyv:</strong> ' . esc_html($this->ownerFacingTitle($resolved->priceBook, false)) . '.</p></div>';
        } catch (NoActivePriceBookException $exception) {
            echo '<div class="notice notice-warning inline"><p>Jelenleg nincs aktív HUF árkönyv. A webshop felvásárlási kalkulátora ezért még nem használhat élő árazást.</p></div>';
        } catch (MultipleActivePriceBooksException $exception) {
            echo '<div class="notice notice-error inline"><p>Több aktív HUF árkönyv található. Az élő árazás biztonsági okból nem oldható fel.</p></div>';
        }
    }

    /** @return array<string,string> */
    private function conditionActions(): array
    {
        return [
            SaveDraftQuestionnaireConditionsHandler::ACTION_SYSTEM_DEFAULT => 'Rendszer alapértelmezése',
            SaveDraftQuestionnaireConditionsHandler::ACTION_NONE => 'Nincs változás',
            SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED => 'Fix levonás',
            SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE => 'Százalékos levonás',
            SaveDraftQuestionnaireConditionsHandler::ACTION_MANUAL_REVIEW => 'Kézi bevizsgálás',
            SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT => 'Nem vásároljuk fel',
        ];
    }

    /** @return array<string,string> */
    private function serviceHistoryComponentActions(bool $allowsMonetary): array
    {
        $actions = [
            SaveDraftQuestionnaireConditionsHandler::ACTION_INHERIT => 'Örökli a szervizelőzmény szabályát',
            SaveDraftQuestionnaireConditionsHandler::ACTION_NONE => 'Nincs változás',
        ];
        if ($allowsMonetary) {
            $actions[SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED] = 'Fix levonás';
            $actions[SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE] = 'Százalékos levonás';
        }
        $actions[SaveDraftQuestionnaireConditionsHandler::ACTION_MANUAL_REVIEW] = 'Személyes bevizsgálás';
        $actions[SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT] = 'Nem felvásárolható';
        return $actions;
    }

    /**
     * @param array{answer_key:string,label:string} $serviceHistory
     * @param list<array{component_key:string,label:string,allows_monetary:bool}> $components
     * @param array<string,PricingRule> $rulesByCode
     */
    private function renderServiceHistoryComponentRules(PriceBook $book, string $modelKey, array $serviceHistory, ?PricingRule $serviceRule, ?PricingRule $legacyServiceRule, array $components, array $rulesByCode, bool $readOnly): void
    {
        echo '<details class="ak-service-history-components" data-ak-service-history-components><summary>Alkatrészenkénti szabályok</summary><p class="description">A kiválasztott alkatrész ennél a szervizelőzménynél felülírhatja az örökölt következményt.</p><div class="ak-service-history-component-list">';
        foreach ($components as $component) {
            $rule = $rulesByCode[SaveDraftQuestionnaireConditionsHandler::componentRuleCode($book->id()?->toInt() ?? 0, $modelKey, $serviceHistory['answer_key'], $component['component_key'])] ?? null;
            $action = $rule === null ? SaveDraftQuestionnaireConditionsHandler::ACTION_INHERIT : $this->conditionAction($rule);
            $value = $this->conditionValue($rule, $action);
            $name = 'service_history_components[' . $serviceHistory['answer_key'] . '][' . $component['component_key'] . ']';
            $needsValue = in_array($action, [SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED, SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE], true);
            echo '<article class="ak-service-history-component-row" data-ak-condition-row data-ak-condition-component="1" data-ak-condition-original-action="' . esc_attr($action) . '" data-ak-condition-original-value="' . esc_attr($value === null ? '' : (string) $value) . '"><div class="ak-service-history-component-copy"><strong>' . esc_html($component['label']) . '</strong><p class="ak-condition-inherited">' . esc_html($this->inheritedServiceHistoryResult($serviceRule, $legacyServiceRule, $serviceHistory['answer_key'])) . '</p></div>';
            if ($readOnly) {
                echo '<div class="ak-condition-current">' . esc_html($this->serviceHistoryComponentActionLabel($action, $value)) . '<p>' . esc_html($rule === null ? $this->inheritedServiceHistoryResult($serviceRule, $legacyServiceRule, $serviceHistory['answer_key']) : 'Forrás: Modell-specifikus alkatrészszabály') . '</p></div></article>';
                continue;
            }
            echo '<label class="ak-condition-action">Művelet<select name="' . esc_attr($name) . '[action]" data-ak-condition-action>';
            foreach ($this->serviceHistoryComponentActions($component['allows_monetary']) as $actionKey => $actionLabel) {
                echo '<option value="' . esc_attr($actionKey) . '" ' . selected($action, $actionKey, false) . '>' . esc_html($actionLabel) . '</option>';
            }
            echo '</select></label>';
            if ($component['allows_monetary']) {
                echo '<label class="ak-condition-value" data-ak-condition-value ' . ($needsValue ? '' : 'hidden') . '>Érték<div class="ak-price-input"><input type="number" min="1" max="' . esc_attr($action === SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE ? '100' : (string) PHP_INT_MAX) . '" step="1" inputmode="numeric" name="' . esc_attr($name) . '[value]" value="' . esc_attr($value === null ? '' : (string) $value) . '" ' . ($needsValue ? '' : 'disabled') . ' data-ak-condition-value-input><span data-ak-condition-unit>' . esc_html($action === SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE ? '%' : 'Ft') . '</span></div></label>';
            }
            echo '</article>';
        }
        echo '</div></details>';
    }

    private function inheritedServiceHistoryResult(?PricingRule $modelRule, ?PricingRule $legacyRule, string $answerKey): string
    {
        $rule = $modelRule ?? $legacyRule;
        if ($rule !== null) {
            $action = $this->conditionAction($rule);
            return 'Jelenlegi örökölt eredmény: ' . $this->conditionActionLabel($action, $this->conditionValue($rule, $action)) . '.';
        }
        $default = (new SystemDefaultQuestionnairePolicy())->entryFor('service_history', $answerKey);
        $action = match ($default['default_action'] ?? PricingRuleKind::NO_CHANGE) {
            PricingRuleKind::MANUAL_REVIEW => SaveDraftQuestionnaireConditionsHandler::ACTION_MANUAL_REVIEW,
            PricingRuleKind::HARD_REJECT => SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT,
            default => SaveDraftQuestionnaireConditionsHandler::ACTION_NONE,
        };
        return 'Jelenlegi örökölt eredmény: ' . $this->conditionActionLabel($action, null) . '.';
    }

    private function serviceHistoryComponentActionLabel(string $action, ?int $value): string
    {
        if ($action === SaveDraftQuestionnaireConditionsHandler::ACTION_INHERIT) {
            return 'Örökli a szervizelőzmény szabályát';
        }
        return $this->conditionActionLabel($action, $value);
    }

    private function conditionAction(?PricingRule $rule): string
    {
        if ($rule === null) {
            return SaveDraftQuestionnaireConditionsHandler::ACTION_NONE;
        }
        return match ($rule->definition()->kind->code()) {
            PricingRuleKind::NO_CHANGE => SaveDraftQuestionnaireConditionsHandler::ACTION_NONE,
            PricingRuleKind::FIXED_DEDUCTION => SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED,
            PricingRuleKind::MULTIPLIER => SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE,
            PricingRuleKind::MANUAL_REVIEW => SaveDraftQuestionnaireConditionsHandler::ACTION_MANUAL_REVIEW,
            PricingRuleKind::HARD_REJECT => SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT,
            default => SaveDraftQuestionnaireConditionsHandler::ACTION_NONE,
        };
    }

    private function conditionValue(?PricingRule $rule, string $action): ?int
    {
        if ($rule === null) {
            return null;
        }
        if ($action === SaveDraftQuestionnaireConditionsHandler::ACTION_FIXED) {
            return $rule->definition()->amount?->amount();
        }
        if ($action === SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE) {
            $basisPoints = $rule->definition()->multiplier?->value();
            return $basisPoints === null ? null : max(0, intdiv(10000 - $basisPoints, 100));
        }
        return null;
    }

    private function conditionActionLabel(string $action, ?int $value): string
    {
        $label = $this->conditionActions()[$action] ?? 'Nincs változás';
        if ($value === null) {
            return $label;
        }
        return $label . ': ' . number_format_i18n($value) . ($action === SaveDraftQuestionnaireConditionsHandler::ACTION_PERCENTAGE ? '%' : ' Ft');
    }

    private function conditionSourceLabel(?PricingRule $modelRule, ?PricingRule $globalRule, string $questionKey, string $answerKey): string
    {
        if ($modelRule !== null) {
            return 'Forrás: Modell-specifikus szabály · ' . $this->conditionActionLabel($this->conditionAction($modelRule), $this->conditionValue($modelRule, $this->conditionAction($modelRule)));
        }
        if ($globalRule !== null) {
            return 'Forrás: Globális árkönyvszabály · ' . $this->conditionActionLabel($this->conditionAction($globalRule), $this->conditionValue($globalRule, $this->conditionAction($globalRule)));
        }
        $entry = (new SystemDefaultQuestionnairePolicy())->entryFor($questionKey, $answerKey);
        if ($entry === null || $entry['default_action'] === PricingRuleKind::NO_CHANGE) {
            return 'Forrás: Rendszer alapértelmezése · Nincs változás';
        }
        return 'Forrás: Rendszer alapértelmezése · ' . $this->conditionActionLabel(match ($entry['default_action']) {
            PricingRuleKind::MANUAL_REVIEW => SaveDraftQuestionnaireConditionsHandler::ACTION_MANUAL_REVIEW,
            PricingRuleKind::HARD_REJECT => SaveDraftQuestionnaireConditionsHandler::ACTION_HARD_REJECT,
            default => SaveDraftQuestionnaireConditionsHandler::ACTION_NONE,
        }, null);
    }

    /** @return array<string,string> */
    private function batteryActions(): array
    {
        return [
            SaveDraftBatteryBandsHandler::ACTION_NONE => 'Nincs változás',
            SaveDraftBatteryBandsHandler::ACTION_FIXED => 'Fix levonás',
            SaveDraftBatteryBandsHandler::ACTION_PERCENTAGE => 'Százalékos levonás',
            SaveDraftBatteryBandsHandler::ACTION_MANUAL_REVIEW => 'Kézi bevizsgálás',
            SaveDraftBatteryBandsHandler::ACTION_HARD_REJECT => 'Nem vásároljuk fel',
        ];
    }

    private function batteryAction(?PricingRule $rule): string
    {
        if ($rule === null) {
            return SaveDraftBatteryBandsHandler::ACTION_NONE;
        }
        return match ($rule->definition()->kind->code()) {
            PricingRuleKind::FIXED_DEDUCTION => SaveDraftBatteryBandsHandler::ACTION_FIXED,
            PricingRuleKind::MULTIPLIER => SaveDraftBatteryBandsHandler::ACTION_PERCENTAGE,
            PricingRuleKind::MANUAL_REVIEW => SaveDraftBatteryBandsHandler::ACTION_MANUAL_REVIEW,
            PricingRuleKind::HARD_REJECT => SaveDraftBatteryBandsHandler::ACTION_HARD_REJECT,
            default => SaveDraftBatteryBandsHandler::ACTION_NONE,
        };
    }

    private function batteryValue(?PricingRule $rule, string $action): ?int
    {
        if ($rule === null) {
            return null;
        }
        if ($action === SaveDraftBatteryBandsHandler::ACTION_FIXED) {
            return $rule->definition()->amount?->amount();
        }
        if ($action === SaveDraftBatteryBandsHandler::ACTION_PERCENTAGE) {
            $basisPoints = $rule->definition()->multiplier?->value();
            return $basisPoints === null ? null : max(0, intdiv(10000 - $basisPoints, 100));
        }
        return null;
    }

    private function batteryActionLabel(string $action, ?int $value): string
    {
        $label = $this->batteryActions()[$action] ?? 'Nincs változás';
        if ($value === null) {
            return $label;
        }
        return $label . ': ' . number_format_i18n($value) . ($action === SaveDraftBatteryBandsHandler::ACTION_PERCENTAGE ? '%' : ' Ft');
    }

    /** @param list<PricingRule> $rules @return list<PricingRule> */
    private function modelBatteryBands(array $rules, string $modelKey): array
    {
        $bands = array_values(array_filter($rules, static function (PricingRule $rule) use ($modelKey): bool {
            $definition = $rule->definition();
            return $definition->enabled
                && $definition->modelKey === $modelKey
                && $definition->conditionKey === 'battery_health'
                && $definition->operator?->code() === ComparisonOperator::BETWEEN
                && is_array($definition->comparisonValue)
                && count($definition->comparisonValue) === 2
                && in_array($definition->kind->code(), [PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER, PricingRuleKind::MANUAL_REVIEW, PricingRuleKind::HARD_REJECT], true);
        }));
        usort($bands, static fn (PricingRule $left, PricingRule $right): int => [(int) $left->definition()->comparisonValue[0], (int) $left->definition()->comparisonValue[1]] <=> [(int) $right->definition()->comparisonValue[0], (int) $right->definition()->comparisonValue[1]]);
        return $bands;
    }

    /** @param list<PricingRule> $rules @return list<PricingRule> */
    private function legacyBatteryBands(array $rules): array
    {
        return array_values(array_filter($rules, static fn (PricingRule $rule): bool => $rule->definition()->enabled && $rule->definition()->modelKey === null && $rule->definition()->conditionKey === 'battery_health'));
    }

    /** @param list<PricingRule> $bands @return array{configured:int,manual:int,reject:int,uncovered:list<array{minimum:int,maximum:int}>} */
    private function batterySummary(array $bands, int $minimum, int $maximum): array
    {
        $manual = 0;
        $reject = 0;
        $covered = [];
        foreach ($bands as $rule) {
            $action = $this->batteryAction($rule);
            if ($action === SaveDraftBatteryBandsHandler::ACTION_MANUAL_REVIEW) {
                ++$manual;
            }
            if ($action === SaveDraftBatteryBandsHandler::ACTION_HARD_REJECT) {
                ++$reject;
            }
            $range = $rule->definition()->comparisonValue;
            $covered[] = ['minimum' => (int) $range[0], 'maximum' => (int) $range[1]];
        }
        usort($covered, static fn (array $left, array $right): int => [$left['minimum'], $left['maximum']] <=> [$right['minimum'], $right['maximum']]);
        $uncovered = [];
        $next = $minimum;
        foreach ($covered as $range) {
            if ($range['minimum'] > $next) {
                $uncovered[] = ['minimum' => $next, 'maximum' => min($maximum, $range['minimum'] - 1)];
            }
            $next = max($next, $range['maximum'] + 1);
        }
        if ($next <= $maximum) {
            $uncovered[] = ['minimum' => $next, 'maximum' => $maximum];
        }
        return ['configured' => count($bands), 'manual' => $manual, 'reject' => $reject, 'uncovered' => $uncovered];
    }

    /** @param list<array{minimum:int,maximum:int}> $ranges */
    private function batteryRangeLabel(array $ranges): string
    {
        if ($ranges === []) {
            return 'Nincs – a publikus tartomány teljesen lefedett.';
        }
        return implode(', ', array_map(static fn (array $range): string => $range['minimum'] . '–' . $range['maximum'] . '%', $ranges));
    }

    private function batteryRuleDescription(PricingRule $rule): string
    {
        $definition = $rule->definition();
        $range = $definition->comparisonValue;
        $scope = is_array($range) && count($range) === 2
            ? (int) $range[0] . '–' . (int) $range[1] . '%'
            : $this->batteryComparisonLabel($definition->operator?->code(), $range);
        return $scope . ': ' . $this->batteryActionLabel($this->batteryAction($rule), $this->batteryValue($rule, $this->batteryAction($rule)));
    }

    private function batteryComparisonLabel(?string $operator, mixed $value): string
    {
        $label = match ($operator) {
            ComparisonOperator::LESS_THAN => 'kevesebb mint',
            ComparisonOperator::LESS_OR_EQUAL => 'legfeljebb',
            ComparisonOperator::GREATER_THAN => 'több mint',
            ComparisonOperator::GREATER_OR_EQUAL => 'legalább',
            ComparisonOperator::EQUALS => 'pontosan',
            default => 'Akkumulátorállapot',
        };
        return $label . ' ' . (is_scalar($value) ? (string) $value . '%' : '');
    }

    /** @param list<DeviceCatalogItem> $models */
    private function selectedConditionModel(array $models): ?DeviceCatalogItem
    {
        $selected = isset($_GET['model']) ? sanitize_key((string) $_GET['model']) : '';
        if ($selected === '' && $models !== []) {
            return $models[0];
        }
        foreach ($models as $model) {
            if ($model->modelKey === $selected) {
                return $model;
            }
        }
        return null;
    }

    /** @param list<PricingRule> $rules */
    private function modelBasePriceStatus(DeviceCatalogItem $model, array $rules): string
    {
        $count = count(array_filter($rules, static fn (PricingRule $rule): bool => $rule->definition()->kind->code() === PricingRuleKind::BASE_PRICE && $rule->definition()->modelKey === $model->modelKey && $rule->definition()->enabled));
        return $count > 0 ? 'Alapár megadva (' . $count . ')' : 'Nincs alapár megadva';
    }

    private function renderNotice(): void
    {
        $lifecycle = get_transient('ak_buyback_lifecycle_notice_' . get_current_user_id());
        if (is_array($lifecycle)) {
            delete_transient('ak_buyback_lifecycle_notice_' . get_current_user_id());
            $message = match ($lifecycle['type'] ?? '') {
                'activation' => 'Az új árkönyv használatba került: ' . (string) $lifecycle['new_label'] . '. A korábbi aktív árkönyv átkerült a korábban használt árkönyvek közé.',
                'deletion' => 'A piszkozatot és a hozzá tartozó ' . (string) $lifecycle['deleted_rule_count'] . ' szabályt véglegesen töröltük.',
                'protection' => (string) $lifecycle['label'] . ' mostantól védett alapárkönyv.',
                'clone' => 'Az új piszkozat elkészült: ' . (string) $lifecycle['label'] . ' (azonosító: ' . (string) $lifecycle['id'] . ').',
                default => '',
            };
            if ($message !== '') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
                if (($lifecycle['type'] ?? '') === 'clone') {
                    return;
                }
            }
        }
        if (! isset($_GET['ak_result'])) {
            return;
        }
        $success = sanitize_key((string) $_GET['ak_result']) === 'success';
        $errorMessage = isset($_GET['ak_message']) ? sanitize_text_field((string) wp_unslash($_GET['ak_message'])) : '';
        $action = sanitize_key((string) ($_GET['ak_action'] ?? ''));
        $successMessage = $action === 'discard_draft_price_book'
            ? 'A módosítás és a hozzá tartozó piszkozat-szabályok törölve lettek.'
            : 'A művelet sikeresen befejeződött.';
        echo '<div class="notice ' . ($success ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . esc_html($success ? $successMessage : ($errorMessage !== '' ? $errorMessage : 'A művelet nem hajtható végre. Ellenőrizd az adatokat és a verziót.')) . '</p></div>';
    }

    private function discardErrorMessage(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof PriceBookNotFoundException => 'A kiválasztott módosítás már nem található.',
            $exception instanceof InvalidAggregateOperationException => 'Csak szerkesztés alatt álló módosítás vethető el.',
            $exception instanceof PriceBookHasBusinessReferencesException => 'A módosítás üzleti vagy történeti hivatkozást tartalmaz, ezért nem vethető el.',
            $exception instanceof \InvalidArgumentException => 'A végleges elvetést meg kell erősíteni.',
            default => 'A módosítás elvetése nem sikerült. Kérjük, próbáld újra.',
        };
    }

    private function cloneErrorMessage(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof StaleAggregateVersionException => 'Az árkönyv időközben megváltozott. Frissítsd az oldalt, majd próbáld újra.',
            $exception instanceof PriceBookNotFoundException => 'A kiválasztott árkönyv már nem található.',
            $exception instanceof \InvalidArgumentException => 'A másolási kérés érvénytelen.',
            default => 'Az új piszkozat elkészítése nem sikerült. Kérjük, próbáld újra.',
        };
    }

    private function redirect(string $result, string $action, int $bookId = 0, ?string $tab = null, ?string $message = null, ?string $model = null): never
    {
        $args = ['page' => self::SLUG, 'ak_result' => $result, 'ak_action' => $action];
        if ($bookId > 0) {
            $args['book_id'] = $bookId;
        }
        if ($bookId > 0 && $tab !== null) {
            $args['tab'] = $tab;
        }
        if ($message !== null && $message !== '') {
            $args['ak_message'] = $message;
        }
        if ($bookId > 0 && in_array($tab, [self::TAB_BASE_PRICES, self::TAB_CONDITIONS, self::TAB_BATTERY], true) && $model !== null && $model !== '') {
            $args['model'] = $model;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function editUrl(PriceBookId $id): string { return add_query_arg(['page' => self::SLUG, 'book_id' => $id->toInt()], admin_url('admin.php')); }
    private function postedInt(string $key): int { return isset($_POST[$key]) ? absint($_POST[$key]) : 0; }
    private function tabField(string $tab): void { echo '<input type="hidden" name="editor_tab" value="' . esc_attr($tab) . '">'; }
    private function postedTab(): ?string { return isset($_POST['editor_tab']) ? $this->normalizeTab(sanitize_key((string) wp_unslash($_POST['editor_tab']))) : null; }
    private function postedModel(): ?string
    {
        $key = match ($this->postedTab()) {
            self::TAB_BASE_PRICES => 'model_minimum_model_key',
            self::TAB_BATTERY => 'battery_model_key',
            default => 'condition_model_key',
        };
        return isset($_POST[$key]) ? sanitize_key((string) wp_unslash($_POST[$key])) : null;
    }
    private function resolveTab(): string { return $this->normalizeTab(sanitize_key((string) ($_GET['tab'] ?? ''))); }
    private function normalizeTab(string $tab): string { return in_array($tab, self::EDITOR_TABS, true) ? $tab : self::TAB_BASE_PRICES; }
    private function tabUrl(int $bookId, string $tab): string { return add_query_arg(['page' => self::SLUG, 'book_id' => $bookId, 'tab' => $tab], admin_url('admin.php')); }

    private function requiredPositiveInt(array $post, string $key): int
    {
        $value = filter_var($post[$key] ?? null, FILTER_VALIDATE_INT);
        if (! is_int($value) || $value < 1) { throw new \InvalidArgumentException('Pozitív egész szám szükséges: ' . $key); }
        return $value;
    }

    private function requiredNonNegativeInt(array $post, string $key): int
    {
        $value = filter_var($post[$key] ?? null, FILTER_VALIDATE_INT);
        if (! is_int($value) || $value < 0) { throw new \InvalidArgumentException('Nem negatív egész szám szükséges: ' . $key); }
        return $value;
    }

    private function textField(string $name, string $label, string $value, bool $required): void { echo '<p><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label><input type="text" class="regular-text" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . ($required ? 'required' : '') . '></p>'; }
    private function numberField(string $name, string $label, ?int $value, int $min, bool $required = true): void { echo '<p><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label><input type="number" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr($value === null ? '' : (string) $value) . '" min="' . esc_attr((string) $min) . '" step="1" ' . ($required ? 'required' : '') . '></p>'; }

    private function policySelect(string $selected): void
    {
        echo '<p><label for="minimum_policy">Minimum alatti ajánlat</label><select name="minimum_policy" id="minimum_policy"><option value="manual_review" ' . selected($selected, 'manual_review', false) . '>Kézi ellenőrzés</option><option value="reject" ' . selected($selected, 'reject', false) . '>Elutasítás</option></select></p>';
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            PricingRuleKind::BASE_PRICE => 'Alapár', PricingRuleKind::FIXED_DEDUCTION => 'Fix levonás', PricingRuleKind::MULTIPLIER => 'Szorzó', PricingRuleKind::MODE_ADJUSTMENT => 'Átvételi mód korrekció', PricingRuleKind::HARD_REJECT => 'Automatikus elutasítás', PricingRuleKind::MANUAL_REVIEW => 'Kézi ellenőrzés', default => $kind,
        };
    }

    private function statusLabel(PriceBook $book): string
    {
        return match ($book->status()->code()) {
            PriceBookStatus::DRAFT => 'Piszkozat',
            PriceBookStatus::ACTIVE => 'Aktív',
            PriceBookStatus::RETIRED => 'Korábbi',
            default => $book->status()->code(),
        };
    }

    private function readinessMessage(string $code): string
    {
        return match ($code) {
            'missing_base_price' => 'Az árkönyv nem tartalmaz aktív alapárat.',
            'duplicate_base_price' => 'Ugyanahhoz a modellhez és tárhelyhez több aktív alapár tartozik.',
            'duplicate_mode_adjustment' => 'Egy átvételi módhoz több aktív korrekció tartozik.',
            'duplicate_model_minimum_offer' => 'Egy modellhez több automatikus ajánlati minimum tartozik.',
            'invalid_rule_shape' => 'Az egyik szabály mezői ellentmondásosak vagy hiányosak.',
            'unknown_condition_key' => 'Az egyik szabály nem támogatott feltételt használ.',
            'unsupported_category' => 'Az árkönyv csak iPhone-modelleket tartalmazhat a V1-ben.',
            'unknown_model_key' => 'Az egyik alapár ismeretlen iPhone-modellre hivatkozik.',
            'invalid_storage' => 'Az egyik alapár nem támogatott tárhelyet használ.',
            'unsupported_service_mode' => 'Az egyik szabály nem támogatott átvételi módot használ.',
            'invalid_rounding_increment' => 'A kerekítési lépés érvénytelen.',
            'invalid_minimum_policy' => 'A minimum ajánlati szabály érvénytelen.',
            'price_book_not_draft' => 'Csak piszkozat állapotú árkönyv aktiválható.',
            'invalid_currency' => 'A V1 kizárólag HUF árkönyvet támogat.',
            'rule_price_book_mismatch' => 'Az egyik szabály másik árkönyvhöz tartozik.',
            'catalog_unavailable' => 'Az iPhone készülékkatalógus jelenleg nem érhető el.',
            'no_hard_reject_rules' => 'Nincs automatikus elutasítási szabály.',
            'no_manual_review_rules' => 'Nincs kézi ellenőrzési szabály.',
            'no_fixed_deduction_rules' => 'Nincs fix levonási szabály.',
            'no_condition_multiplier_rules' => 'Nincs állapotszorzó szabály.',
            'missing_mode_adjustment_in_store_instant' => 'Az azonnali személyes felvásárlás semleges korrekciót használ.',
            'missing_mode_adjustment_fast_online' => 'A gyors felvásárlás semleges korrekciót használ.',
            'missing_mode_adjustment_higher_offer' => 'A magasabb ajánlat semleges korrekciót használ.',
            'missing_mode_adjustment_trade_in' => 'Az azonnali beszámítás semleges korrekciót használ.',
            default => 'Az ellenőrzés további beállítást igényel.',
        };
    }

    private function ruleValue(PricingRule $rule): string
    {
        $d = $rule->definition();
        if ($d->amount !== null) { return number_format_i18n($d->amount->amount()) . ' Ft'; }
        if ($d->multiplier !== null) { return $this->basisPointsPercent($d->multiplier->value()) . '%'; }
        return $d->publicLabel ?? '–';
    }

    private function basisPointsPercent(int $basisPoints): string { return rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.'); }
    private function comparisonDisplay(mixed $value): string { if (is_array($value)) { return implode(', ', array_map(fn (mixed $v): string => $this->comparisonDisplay($v), $value)); } return is_bool($value) ? ($value ? 'Igen' : 'Nem') : (string) $value; }

    private function serviceModeLabel(string $mode): string
    {
        return $this->offerModes()->all()[$mode]['label'] ?? $mode;
    }

    private function offerModes(): OfferModeConfiguration
    {
        return $this->offerModes ?? OfferModeConfiguration::defaults();
    }

    private function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            PricingOutcome::OFFERED => 'Ajánlható',
            PricingOutcome::MANUAL_REVIEW => 'Kézi ellenőrzés',
            PricingOutcome::REJECTED => 'Elutasítva',
            PricingOutcome::CONFIGURATION_ERROR => 'Árkönyv-konfigurációs hiba',
            default => $outcome,
        };
    }

    private function breakdownLabel(string $type): string
    {
        return match ($type) {
            'base_price' => 'Alapár',
            'fixed_deduction' => 'Fix levonás',
            'multiplier' => 'Állapotszorzó',
            'mode_fixed_adjustment' => 'Mód fix korrekció',
            'mode_multiplier' => 'Módszorzó',
            'minimum_policy' => 'Minimum policy',
            'rounding' => 'Kerekítés',
            default => $type,
        };
    }
}
