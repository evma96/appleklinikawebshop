<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Interfaces\Admin;

use AppleKlinika\Buyback\Application\Command\AddDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\ActivateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\CreateDraftPriceBook;
use AppleKlinika\Buyback\Application\Command\DeleteDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\ToggleDraftPricingRule;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPriceBookSettings;
use AppleKlinika\Buyback\Application\Command\UpdateDraftPricingRule;
use AppleKlinika\Buyback\Application\Exception\DeviceCatalogUnavailableException;
use AppleKlinika\Buyback\Application\Handler\AddDraftPricingRuleHandler;
use AppleKlinika\Buyback\Application\Handler\ActivateDraftPriceBookHandler;
use AppleKlinika\Buyback\Application\Handler\CreateDraftPriceBookHandler;
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
use AppleKlinika\Buyback\Application\Pricing\PriceBookActivationReadinessService;
use AppleKlinika\Buyback\Application\Exception\MultipleActivePriceBooksException;
use AppleKlinika\Buyback\Application\Exception\NoActivePriceBookException;
use AppleKlinika\Buyback\Domain\Pricing\ComparisonOperator;
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
use AppleKlinika\Buyback\Infrastructure\WordPress\CapabilityManager;

final class PriceBooksPage
{
    public const SLUG = 'appleklinika-buyback-price-books';
    private const STORAGE_OPTIONS = [32, 64, 128, 256, 512, 1024];

    public function __construct(
        private readonly PriceBookRepository $books,
        private readonly PricingRuleRepository $rules,
        private readonly DeviceCatalogReader $catalog,
        private readonly CreateDraftPriceBookHandler $createBook,
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
        private readonly AdminSubmissionGuard $submissionGuard
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
        add_submenu_page('woocommerce', 'Apple Klinika Buyback – Árkönyvek', 'Buyback – Árkönyvek', CapabilityManager::MANAGE_PRICE_BOOKS, self::SLUG, [$this, 'render']);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'woocommerce_page_' . self::SLUG) {
            return;
        }
        wp_enqueue_style('appleklinika-buyback-admin', APPLEKLINIKA_BUYBACK_URL . 'assets/admin/price-books.css', [], APPLEKLINIKA_BUYBACK_VERSION);
        wp_enqueue_script('appleklinika-buyback-admin', APPLEKLINIKA_BUYBACK_URL . 'assets/admin/price-books.js', [], APPLEKLINIKA_BUYBACK_VERSION, true);
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
            $this->authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, $nonce);
            $this->dispatch($action, wp_unslash($_POST));
            $this->redirect('success', $action, $this->postedInt('price_book_id'));
        } catch (\Throwable $exception) {
            $this->redirect('error', 'validation', $this->postedInt('price_book_id'));
        }
    }

    public function render(): void
    {
        if (! current_user_can(CapabilityManager::MANAGE_PRICE_BOOKS)) {
            wp_die(esc_html('Nincs jogosultságod az árkönyvek kezeléséhez.'));
        }

        echo '<div class="wrap ak-buyback-admin">';
        echo '<h1>Apple Klinika Buyback – Árkönyvek</h1>';
        $this->renderTabs();
        $this->renderActiveBookNotice();
        $this->renderNotice();

        $bookId = isset($_GET['book_id']) ? absint($_GET['book_id']) : 0;
        if ($bookId > 0) {
            $this->renderEdit(new PriceBookId($bookId));
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

        $bookId = $this->requiredPositiveInt($post, 'price_book_id');
        $bookVersion = $this->requiredNonNegativeInt($post, 'expected_book_version');

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

        if ($action === 'activate_price_book') {
            $this->activateBook->handle(new ActivateDraftPriceBook(
                $bookId,
                $bookVersion,
                get_current_user_id(),
                sanitize_text_field((string) ($post['activation_confirmation'] ?? ''))
            ));
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

    private function renderIndex(): void
    {
        $page = $this->books->list(max(1, absint($_GET['paged'] ?? 1)), 20);
        echo '<div class="ak-buyback-grid"><section class="ak-buyback-card"><h2>Árkönyvek</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Verzió</th><th>Név</th><th>Állapot</th><th>Pénznem</th><th>Szabályok</th><th>Minimum</th><th>Kerekítés</th><th>Hatály</th><th>Frissítve</th><th></th></tr></thead><tbody>';
        foreach ($page->items as $book) {
            $effective = $book->status()->isActive()
                ? 'Ettől: ' . ($book->effectiveFrom()?->format('Y-m-d H:i') ?? '–')
                : ($book->status()->isRetired() ? 'Eddig: ' . ($book->effectiveTo()?->format('Y-m-d H:i') ?? '–') : '–');
            echo '<tr><td>v' . esc_html((string) $book->versionNumber()->value()) . '</td><td>' . esc_html($book->label()) . '</td><td><span class="ak-status">' . esc_html($this->statusLabel($book)) . '</span></td><td>' . esc_html($book->currency()->code()) . '</td><td>' . esc_html((string) $this->rules->countForPriceBook($book->id())) . '</td><td>' . esc_html(number_format_i18n($book->minimumOffer()->amount())) . ' Ft</td><td>' . esc_html(number_format_i18n($book->roundingIncrementMinor())) . ' Ft</td><td>' . esc_html($effective) . '</td><td>' . esc_html($book->updatedAt()->format('Y-m-d H:i')) . '</td><td><a class="button" href="' . esc_url($this->editUrl($book->id())) . '">' . esc_html($book->status()->isDraft() ? 'Szerkesztés' : 'Megtekintés') . '</a></td></tr>';
        }
        if ($page->items === []) {
            echo '<tr><td colspan="10">Még nincs árkönyv. Hozd létre az első piszkozatot.</td></tr>';
        }
        echo '</tbody></table></section>';
        echo '<section class="ak-buyback-card"><h2>Új piszkozat</h2><form method="post">';
        $this->securityFields('create_price_book');
        echo '<input type="hidden" name="submission_token" value="' . esc_attr($this->submissionGuard->issue()) . '">';
        $this->textField('label', 'Megnevezés', '', true);
        echo '<p><label>Pénznem</label><input type="text" value="HUF" readonly></p>';
        $this->numberField('minimum_offer_minor', 'Minimum ajánlat (Ft)', 0, 0);
        $this->numberField('rounding_increment_minor', 'Kerekítési lépés (Ft)', 1000, 1);
        $this->policySelect(MinimumOfferPolicy::MANUAL_REVIEW);
        submit_button('Piszkozat létrehozása');
        echo '</form></section></div>';
    }

    private function renderEdit(PriceBookId $id): void
    {
        $book = $this->books->getById($id);
        if ($book === null) {
            echo '<div class="notice notice-error"><p>Az árkönyv nem található.</p></div>';
            return;
        }
        $rules = $this->rules->listForPriceBook($id);
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">← Vissza az árkönyvekhez</a></p>';
        $lifecycleText = $book->status()->isDraft()
            ? 'Nem aktív, és semmilyen ügyfélfolyamat nem használja.'
            : ($book->status()->isActive() ? 'Az aktív árkönyv és szabályai csak olvashatók.' : 'Az archivált árkönyv és szabályai változatlan előzményként megmaradnak.');
        echo '<div class="ak-buyback-heading"><div><h2>v' . esc_html((string) $book->versionNumber()->value()) . ' – ' . esc_html($book->label()) . '</h2><span class="ak-status">' . esc_html($this->statusLabel($book)) . '</span></div><p>' . esc_html($lifecycleText) . '</p></div>';

        if (! $book->status()->isDraft()) {
            echo '<div class="notice notice-info inline"><p>Ez az árkönyv csak olvasható. Aktív vagy archivált árkönyv és annak szabályai nem módosíthatók.</p></div>';
            $this->renderRulesTable($book, $rules);
            return;
        }

        echo '<div class="ak-buyback-grid"><section class="ak-buyback-card"><h3>Beállítások</h3><form method="post">';
        $this->securityFields('update_price_book', $book);
        $this->textField('label', 'Megnevezés', $book->label(), true);
        $this->numberField('minimum_offer_minor', 'Minimum ajánlat (Ft)', $book->minimumOffer()->amount(), 0);
        $this->numberField('rounding_increment_minor', 'Kerekítési lépés (Ft)', $book->roundingIncrementMinor(), 1);
        $this->policySelect($book->minimumPolicy()->code());
        submit_button('Beállítások mentése');
        echo '</form></section>';

        $editRule = isset($_GET['rule_id']) ? $this->rules->getById(new PricingRuleId(absint($_GET['rule_id']))) : null;
        if ($editRule !== null && ! $editRule->priceBookId()->equals($id)) {
            $editRule = null;
        }
        echo '<section class="ak-buyback-card"><h3>' . ($editRule === null ? 'Új szabály' : 'Szabály szerkesztése') . '</h3>';
        $this->renderRuleForm($book, $editRule);
        echo '</section></div>';
        $this->renderRulesTable($book, $rules);
        $this->renderCalculationPreview($book);
        $this->renderActivationReadiness($book, $rules);
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
        if ($report->ready) {
            echo '<div class="notice notice-warning inline"><p>Az aktiválás után az árkönyv és szabályai nem szerkeszthetők. Ha már van aktív HUF árkönyv, az automatikusan archiválásra kerül.</p></div>';
            echo '<form method="post" class="ak-activation-form">';
            $this->securityFields('activate_price_book', $book);
            $this->textField('activation_confirmation', 'Megerősítés: AKTIVÁLOM', '', true);
            submit_button('Árkönyv aktiválása', 'primary');
            echo '</form>';
        } else {
            echo '<p><strong>Az aktiválás nem érhető el, amíg minden blokkoló hiba meg nem szűnik.</strong></p>';
        }
        echo '</section>';
    }

    private function renderCalculationPreview(PriceBook $book): void
    {
        $preview = null;
        $error = null;
        $posted = isset($_POST['ak_buyback_action']) && sanitize_key((string) wp_unslash($_POST['ak_buyback_action'])) === 'preview_calculation';
        $post = $posted ? wp_unslash($_POST) : [];

        if ($posted) {
            try {
                $nonce = sanitize_text_field((string) ($post['_ak_buyback_nonce'] ?? ''));
                $this->authorization->assert(CapabilityManager::MANAGE_PRICE_BOOKS, $nonce);
                $query = $this->previewParser->parse($post);
                if ($query->priceBookId !== $book->id()?->toInt()) {
                    throw new \InvalidArgumentException('Az előnézet árkönyve nem egyezik a megnyitott árkönyvvel.');
                }
                $preview = $this->previewHandler->handle($query);
            } catch (\Throwable $exception) {
                $error = 'Az előnézet nem számítható ki. Ellenőrizd az összes mezőt és az árkönyv szabályait.';
            }
        }

        echo '<section class="ak-buyback-card ak-pricing-preview"><h3>Kalkulációs előnézet</h3>';
        echo '<div class="notice notice-warning inline"><p>Ez csak adminisztrációs előnézet. Nem hoz létre ajánlatot, nem ment kalkulációt, és a webshop nem használja.</p></div>';
        if ($error !== null) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($error) . '</p></div>';
        }

        echo '<form method="post" class="ak-preview-form">';
        $this->securityFields('preview_calculation', $book);
        echo '<div class="ak-preview-device">';
        echo '<p><label for="preview_model_key">iPhone modell</label><select name="preview_model_key" id="preview_model_key" required><option value="">Válassz modellt</option>';
        try {
            foreach ($this->catalog->iPhoneModels() as $item) {
                echo '<option value="' . esc_attr($item->modelKey) . '" ' . selected((string) ($post['preview_model_key'] ?? ''), $item->modelKey, false) . '>' . esc_html($item->label) . '</option>';
            }
        } catch (DeviceCatalogUnavailableException $exception) {
            echo '<option value="">A készülékkatalógus nem érhető el</option>';
        }
        echo '</select></p><p><label for="preview_storage_gb">Tárhely</label><select name="preview_storage_gb" id="preview_storage_gb" required><option value="">Válassz tárhelyet</option>';
        foreach (self::STORAGE_OPTIONS as $storage) {
            echo '<option value="' . esc_attr((string) $storage) . '" ' . selected((string) ($post['preview_storage_gb'] ?? ''), (string) $storage, false) . '>' . esc_html((string) $storage) . ' GB</option>';
        }
        echo '</select></p></div><div class="ak-preview-conditions">';
        $rawConditions = isset($post['preview_conditions']) && is_array($post['preview_conditions']) ? $post['preview_conditions'] : [];
        foreach (ConditionDefinition::all() as $key => $definition) {
            $fieldName = 'preview_conditions[' . $key . ']';
            $fieldId = 'preview_condition_' . $key;
            $current = is_scalar($rawConditions[$key] ?? null) ? (string) $rawConditions[$key] : '';
            echo '<p><label for="' . esc_attr($fieldId) . '">' . esc_html($definition['label']) . '</label>';
            if ($definition['type'] === ConditionDefinition::TYPE_INTEGER) {
                echo '<input type="number" min="0" max="100" step="1" name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" value="' . esc_attr($current) . '" required>';
            } else {
                echo '<select name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" required><option value="">Válassz</option>';
                $options = $definition['type'] === ConditionDefinition::TYPE_BOOLEAN
                    ? ['1' => 'Igen', '0' => 'Nem']
                    : $definition['values'];
                foreach ($options as $value => $label) {
                    echo '<option value="' . esc_attr((string) $value) . '" ' . selected($current, (string) $value, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
            }
            echo '</p>';
        }
        echo '</div>';
        submit_button('Négy átvételi mód kiszámítása', 'secondary');
        echo '</form>';

        if ($preview !== null) {
            echo '<div class="ak-preview-results">';
            foreach ($preview->modeResults as $mode => $result) {
                $this->renderPreviewResult($mode, $result);
            }
            echo '</div>';
        }
        echo '</section>';
    }

    private function renderPreviewResult(string $mode, PricingCalculationResult $result): void
    {
        echo '<article class="ak-preview-result"><h4>' . esc_html($this->serviceModeLabel($mode)) . '</h4>';
        echo '<p><span class="ak-status">' . esc_html($this->outcomeLabel($result->outcome->code())) . '</span></p>';
        if ($result->outcome->code() === PricingOutcome::OFFERED && $result->finalAmount !== null) {
            echo '<p class="ak-preview-amount">' . esc_html(number_format_i18n($result->finalAmount->amount())) . ' Ft</p>';
        }
        if ($result->reasonCodes !== []) {
            echo '<ul class="ak-preview-reasons">';
            foreach ($result->reasonCodes as $reason) {
                echo '<li><code>' . esc_html($reason) . '</code></li>';
            }
            echo '</ul>';
        }
        if ($result->breakdown !== []) {
            echo '<table class="widefat striped"><thead><tr><th>Lépés</th><th>Szabály</th><th>Előtte</th><th>Változás</th><th>Utána</th></tr></thead><tbody>';
            foreach ($result->breakdown as $line) {
                $change = $line->multiplierBps !== null
                    ? $this->basisPointsPercent($line->multiplierBps) . '%'
                    : ($line->adjustmentAmountMinor === null ? '–' : number_format_i18n($line->adjustmentAmountMinor) . ' Ft');
                echo '<tr><td>' . esc_html($this->breakdownLabel($line->type)) . '</td><td><code>' . esc_html($line->ruleCode ?? '–') . '</code></td><td>' . esc_html(number_format_i18n($line->beforeAmountMinor)) . ' Ft</td><td>' . esc_html($change) . '</td><td>' . esc_html(number_format_i18n($line->afterAmountMinor)) . ' Ft</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</article>';
    }

    /** @param list<PricingRule> $rules */
    private function renderRulesTable(PriceBook $book, array $rules): void
    {
        echo '<section class="ak-buyback-card ak-buyback-rules"><h3>Árazási szabályok</h3><table class="widefat striped"><thead><tr><th>Prioritás</th><th>Kód</th><th>Típus</th><th>Cél</th><th>Érték</th><th>Állapot</th><th>Művelet</th></tr></thead><tbody>';
        foreach ($rules as $rule) {
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
        if ($rules === []) {
            echo '<tr><td colspan="7">Ehhez a piszkozathoz még nincs szabály.</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    private function renderRuleForm(PriceBook $book, ?PricingRule $rule): void
    {
        $definition = $rule?->definition();
        echo '<form method="post" class="ak-rule-form">';
        $this->securityFields($rule === null ? 'add_rule' : 'update_rule', $book, $rule);
        $this->textField('rule_code', 'Szabálykód', $definition?->code->code() ?? '', true);
        echo '<p><label for="rule_kind">Szabálytípus</label><select name="rule_kind" id="rule_kind" data-ak-rule-kind>';
        foreach ([PricingRuleKind::BASE_PRICE, PricingRuleKind::FIXED_DEDUCTION, PricingRuleKind::MULTIPLIER, PricingRuleKind::MODE_ADJUSTMENT, PricingRuleKind::HARD_REJECT, PricingRuleKind::MANUAL_REVIEW] as $kind) {
            echo '<option value="' . esc_attr($kind) . '" ' . selected($definition?->kind->code() ?? PricingRuleKind::BASE_PRICE, $kind, false) . '>' . esc_html($this->kindLabel($kind)) . '</option>';
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

    private function renderTabs(): void
    {
        echo '<nav class="nav-tab-wrapper"><a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=' . DiagnosticsPage::SLUG)) . '">Diagnosztika</a><a class="nav-tab nav-tab-active" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Árkönyvek</a></nav>';
    }

    private function renderActiveBookNotice(): void
    {
        try {
            $resolved = $this->activePriceBookResolver->resolveForCurrencyAt(new CurrencyCode('HUF'), $this->clock->now());
            echo '<div class="notice notice-success inline"><p><strong>Aktív HUF árkönyv:</strong> v' . esc_html((string) $resolved->priceBook->versionNumber()->value()) . ' – ' . esc_html($resolved->priceBook->label()) . '.</p></div>';
        } catch (NoActivePriceBookException $exception) {
            echo '<div class="notice notice-warning inline"><p>Jelenleg nincs aktív HUF árkönyv. A webshop felvásárlási kalkulátora ezért még nem használhat élő árazást.</p></div>';
        } catch (MultipleActivePriceBooksException $exception) {
            echo '<div class="notice notice-error inline"><p>Több aktív HUF árkönyv található. Az élő árazás biztonsági okból nem oldható fel.</p></div>';
        }
    }

    private function renderNotice(): void
    {
        if (! isset($_GET['ak_result'])) {
            return;
        }
        $success = sanitize_key((string) $_GET['ak_result']) === 'success';
        echo '<div class="notice ' . ($success ? 'notice-success' : 'notice-error') . ' is-dismissible"><p>' . esc_html($success ? 'A művelet sikeresen befejeződött.' : 'A művelet nem hajtható végre. Ellenőrizd az adatokat és a verziót.') . '</p></div>';
    }

    private function redirect(string $result, string $action, int $bookId = 0): never
    {
        $args = ['page' => self::SLUG, 'ak_result' => $result, 'ak_action' => $action];
        if ($bookId > 0) {
            $args['book_id'] = $bookId;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function editUrl(PriceBookId $id): string { return add_query_arg(['page' => self::SLUG, 'book_id' => $id->toInt()], admin_url('admin.php')); }
    private function postedInt(string $key): int { return isset($_POST[$key]) ? absint($_POST[$key]) : 0; }

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
            PriceBookStatus::RETIRED => 'Archivált',
            default => $book->status()->code(),
        };
    }

    private function readinessMessage(string $code): string
    {
        return match ($code) {
            'missing_base_price' => 'Az árkönyv nem tartalmaz aktív alapárat.',
            'duplicate_base_price' => 'Ugyanahhoz a modellhez és tárhelyhez több aktív alapár tartozik.',
            'duplicate_mode_adjustment' => 'Egy átvételi módhoz több aktív korrekció tartozik.',
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
        return match ($mode) {
            'in_store_instant' => 'Azonnali személyes felvásárlás',
            'fast_online' => 'Gyors felvásárlás',
            'higher_offer' => 'Magasabb ajánlat',
            'trade_in' => 'Azonnali beszámítás',
            default => $mode,
        };
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
