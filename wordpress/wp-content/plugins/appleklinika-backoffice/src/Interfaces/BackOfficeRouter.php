<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Domain\DeliveryMode;
use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
use InvalidArgumentException;
use WC_Order;

final class BackOfficeRouter
{
    public const CAPABILITY = 'manage_appleklinika_backoffice';
    private const QUERY_VAR = 'appleklinika_backoffice';

    public function __construct(private readonly WooOrderBackOfficeRepository $orders)
    {
    }

    public function register(): void
    {
        add_action('init', [self::class, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'queryVars']);
        add_action('template_redirect', [$this, 'render']);
        add_action('admin_post_appleklinika_backoffice_action', [$this, 'handleAction']);
        add_action('admin_post_appleklinika_backoffice_download_label', [$this, 'handleLabelDownload']);
        add_action('woocommerce_checkout_create_order_line_item', [$this->orders, 'captureDeviceIdentifierSnapshot'], 10, 4);
        add_action('woocommerce_checkout_order_created', [$this->orders, 'captureQueueShippingSnapshot']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'renderCustomerProgress'], 15);
        add_action('wp_enqueue_scripts', [$this, 'enqueueCustomerProgressStyle']);
    }

    public static function registerRewriteRules(): void
    {
        add_rewrite_rule('^backoffice/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
    }

    /** @param list<string> $vars @return list<string> */
    public function queryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function render(): void
    {
        if ((string) get_query_var(self::QUERY_VAR) !== '1') {
            return;
        }

        $this->requireAccess();
        show_admin_bar(false);
        nocache_headers();

        $orderId = isset($_GET['order']) ? absint(wp_unslash($_GET['order'])) : 0;
        $print = isset($_GET['print']) && (string) wp_unslash($_GET['print']) === '1';
        if ($orderId > 0) {
            $order = wc_get_order($orderId);
            if (! $order instanceof WC_Order) {
                status_header(404);
                wp_die('A rendelés nem található.', 'Rendelés nem található', ['response' => 404]);
            }

            $print ? $this->renderPackingSheet($order) : $this->renderOrder($order, $this->worklistContext($_GET));
            exit;
        }

        $this->renderQueue();
        exit;
    }

    public function handleAction(): void
    {
        $this->requireAccess();
        $orderId = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $action = isset($_POST['backoffice_action']) ? sanitize_key(wp_unslash($_POST['backoffice_action'])) : '';
        $worklistContext = $this->worklistContext($_POST);
        $order = $orderId > 0 ? wc_get_order($orderId) : false;

        if (! $order instanceof WC_Order || $action === '') {
            $this->redirect($orderId, 'Érvénytelen Back Office kérés.', true, $worklistContext);
        }

        check_admin_referer('appleklinika_backoffice_' . $action . '_' . $orderId);

        try {
            if ($action === 'note') {
                $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
                $this->orders->addInternalNote($order, $note);
                $this->redirect($orderId, 'A belső megjegyzés elmentve.', false, $worklistContext);
            }

            if (! array_key_exists($action, FulfilmentWorkflow::actions())) {
                throw new InvalidArgumentException('Ismeretlen Back Office művelet.');
            }

            $blockReason = $this->orders->fulfilmentBlockReason($order);
            if ($blockReason !== null) {
                throw new InvalidArgumentException($blockReason);
            }

            $deliveryMode = $this->orders->deliveryMode($order);

            if ($action === 'create_label') {
                if ($deliveryMode !== DeliveryMode::GLS) {
                    throw new InvalidArgumentException('GLS címke csak GLS kézbesítésű rendeléshez hozható létre.');
                }
                if ($this->orders->hasGlsLabel($order)) {
                    throw new InvalidArgumentException('Ehhez a rendeléshez már létezik GLS címke.');
                }
                $this->orders->createGlsLabel($order);
            }
            if ($action === 'handed_to_gls' && ! $this->orders->hasGlsLabel($order)) {
                throw new InvalidArgumentException('A GLS-nek átadás csak létrehozott GLS címke után rögzíthető.');
            }

            $this->orders->transition($order, $action, get_current_user_id());
            $state = $this->orders->state($order);
            $notice = $action === 'create_label'
                ? 'GLS címke létrehozva. A rendelés állapota változatlanul: ' . FulfilmentWorkflow::labels()[$state] . '.'
                : 'Rendelés állapota frissítve: ' . FulfilmentWorkflow::labels()[$state] . '.';
            $this->redirect($orderId, $notice, false, $worklistContext);
        } catch (InvalidArgumentException $exception) {
            $this->redirect($orderId, $exception->getMessage(), true, $worklistContext);
        } catch (\Throwable $exception) {
            $this->redirect($orderId, 'A művelet nem hajtható végre biztonságosan.', true, $worklistContext);
        }
    }

    public function handleLabelDownload(): void
    {
        $this->requireAccess();
        $orderId = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
        $order = $orderId > 0 ? wc_get_order($orderId) : false;
        if (! $order instanceof WC_Order) {
            wp_die('A rendelés nem található.', 'Rendelés nem található', ['response' => 404]);
        }

        check_admin_referer('appleklinika_backoffice_download_label_' . $orderId);
        $filename = sanitize_file_name((string) $order->get_meta('_gls_print_label', true));
        if ($filename === '' || ! defined('GLS_LABELS_DIR')) {
            wp_die('A GLS címke nem érhető el.', 'Címke nem található', ['response' => 404]);
        }

        $labelsDirectory = realpath(GLS_LABELS_DIR);
        $labelPath = realpath(trailingslashit(GLS_LABELS_DIR) . $filename);
        if ($labelsDirectory === false || $labelPath === false || ! str_starts_with($labelPath, trailingslashit($labelsDirectory))) {
            wp_die('Érvénytelen GLS címke.', 'Címke nem található', ['response' => 404]);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($labelPath) . '"');
        header('Content-Length: ' . (string) filesize($labelPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($labelPath);
        exit;
    }

    private function requireAccess(): void
    {
        if (! is_user_logged_in()) {
            auth_redirect();
        }

        if (! current_user_can(self::CAPABILITY) && ! current_user_can('manage_options')) {
            status_header(403);
            wp_die('Nincs jogosultságod az Apple Klinika Back Office használatához.', 'Hozzáférés megtagadva', ['response' => 403]);
        }
    }

    private function renderQueue(): void
    {
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        if ($view === 'activity') {
            $this->renderTodayActivity();
            return;
        }

        $worklistContext = $this->worklistContext($_GET);
        $queue = (string) ($worklistContext['queue'] ?? '');
        $search = (string) ($worklistContext['s'] ?? '');
        $requestedSearchType = (string) ($worklistContext['search_type'] ?? '');
        $page = (int) ($worklistContext['queue_page'] ?? 1);
        $searchType = $this->orders->searchType($search, $requestedSearchType);
        $result = $this->orders->queuePage($queue, $page, $search, $requestedSearchType);
        $orders = $result->orders;
        $total = (int) $result->total;
        $pageCount = max(1, (int) $result->max_num_pages);
        $counts = $this->orders->queueCounts();

        $this->documentStart('Apple Klinika Back Office');
        echo '<main class="akbo-shell">';
        $this->renderApplicationHeader('Nyitott rendelések');
        $this->notice();

        echo '<section class="akbo-summary" aria-label="Nyitott rendelési összesítő">';
        foreach (['new' => 'Feldolgozásra vár', 'preparation' => 'Előkészítés alatt', 'packing' => 'Csomagolás alatt', 'ready_for_shipping' => 'Átadásra előkészítve', 'problem' => 'Probléma'] as $key => $label) {
            echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html((string) ($counts[$key] ?? 0)) . '</strong></div>';
        }
        echo '</section>';

        echo '<form class="akbo-search" method="get" action="' . esc_url(home_url('/backoffice/')) . '">';
        echo '<label for="akbo-queue">Állapot</label><select id="akbo-queue" name="queue">';
        foreach (['' => 'Összes nyitott', 'new' => 'Feldolgozásra vár', 'preparation' => 'Előkészítés alatt', 'packing' => 'Csomagolás alatt', 'ready_for_shipping' => 'Átadásra előkészítve', 'handed_to_gls' => 'Teljesítve', 'problem' => 'Problémás'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($queue, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><label for="akbo-search">Keresés a nyitott rendeléseken</label>';
        echo '<div><input id="akbo-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rendelés, név, e-mail, telefon vagy eszközazonosító">';
        echo '<select name="search_type" aria-label="Keresés típusa">';
        foreach (['' => 'Automatikus', 'order' => 'Rendelésszám', 'customer' => 'Ügyfélnév', 'email' => 'E-mail', 'phone' => 'Telefon', 'device' => 'IMEI / belső azonosító'] as $type => $label) {
            echo '<option value="' . esc_attr($type) . '" ' . selected($requestedSearchType, $type, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><button type="submit">Keresés</button></div>';
        if ($search !== '') {
            echo '<p class="akbo-help">Aktív keresési típus: <strong>' . esc_html($this->searchTypeLabel($searchType)) . '</strong>.</p>';
        }
        echo '</form>';

        echo '<section class="akbo-card"><p class="akbo-result-count">' . esc_html((string) $total) . ' találat · ' . esc_html((string) $page) . '/' . esc_html((string) $pageCount) . '. oldal</p><div class="akbo-table-wrap"><table class="akbo-table"><thead><tr><th>Rendelés</th><th>Ügyfél</th><th>Készülék</th><th>Fizetés / figyelem</th><th>Back Office állapot</th><th>Szállítás</th></tr></thead><tbody>';
        if ($orders === []) {
            echo '<tr><td colspan="6" class="akbo-empty">Nincs a feltételnek megfelelő rendelés.</td></tr>';
        }
        foreach ($orders as $order) {
            $primaryDevice = $this->orders->queuePrimaryItem($order);
            $state = $this->orders->state($order);
            $attention = $this->attention($order, $state);
            echo '<tr>';
            echo '<td><a href="' . esc_url($this->orderUrl($order->get_id(), $worklistContext)) . '">#' . esc_html($order->get_order_number()) . '</a><small>' . esc_html(wc_format_datetime($order->get_date_created())) . '</small></td>';
            echo '<td>' . esc_html($order->get_formatted_billing_full_name()) . '<small>' . esc_html($order->get_billing_email()) . '</small></td>';
            echo '<td>' . esc_html($primaryDevice) . '</td>';
            echo '<td><span class="akbo-badge">' . esc_html($this->paymentLabel($order)) . '</span>' . ($attention === '' ? '' : '<small class="akbo-attention">' . esc_html($attention) . '</small>') . '</td>';
            echo '<td><span class="akbo-badge akbo-state--' . esc_attr($state) . '">' . esc_html(FulfilmentWorkflow::labels()[$state]) . '</span></td>';
            echo '<td>' . esc_html($this->orders->queueShippingMethod($order)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        $this->renderPagination($queue, $search, $requestedSearchType, $page, $pageCount);
        echo '</section></main>';
        $this->documentEnd();
    }

    public function renderCustomerProgress(WC_Order $order): void
    {
        if (! is_account_page() || ! is_user_logged_in() || $order->get_user_id() !== get_current_user_id()) {
            return;
        }

        $deliveryMode = $this->orders->deliveryMode($order);
        if (! DeliveryMode::isSupported($deliveryMode)) {
            return;
        }

        $state = FulfilmentWorkflow::customerProgressState($this->orders->state($order), $this->orders->history($order), $deliveryMode);
        $labels = FulfilmentWorkflow::customerProgressLabels($deliveryMode);
        $steps = array_keys($labels);
        $currentStep = array_search($state, $steps, true);
        if ($currentStep === false) {
            $currentStep = 0;
        }

        echo '<section class="akbo-customer-progress" aria-labelledby="akbo-customer-progress-title">';
        echo '<h2 id="akbo-customer-progress-title">Apple Klinika rendelési folyamat</h2>';
        echo '<p>Jelenlegi állapot: <strong>' . esc_html($labels[$state]) . '</strong></p>';
        echo '<ol>';
        foreach ($steps as $index => $step) {
            $class = $index < $currentStep ? 'is-complete' : ($index === $currentStep ? 'is-current' : '');
            echo '<li class="' . esc_attr($class) . '">' . esc_html($labels[$step]) . '</li>';
        }
        echo '</ol></section>';
    }

    public function enqueueCustomerProgressStyle(): void
    {
        if (! is_account_page() || ! is_wc_endpoint_url('view-order')) {
            return;
        }

        wp_enqueue_style(
            'appleklinika-backoffice-customer-progress',
            plugins_url('assets/customer-progress.css', dirname(__DIR__, 2) . '/appleklinika-backoffice.php'),
            [],
            '0.1.0'
        );
    }

    private function renderTodayActivity(): void
    {
        $activity = array_reverse($this->orders->todayActivity());
        $counts = FulfilmentWorkflow::employeeDailyCounts($activity);

        $this->documentStart('Mai Back Office aktivitás');
        echo '<main class="akbo-shell">';
        $this->renderApplicationHeader('Mai aktivitás · ' . current_time('Y. m. d.'));
        echo '<section class="akbo-card"><h2>Munkatársanként</h2><div class="akbo-activity-counts">';
        if ($counts === []) {
            echo '<p class="akbo-empty">Ma még nincs Back Office művelet.</p>';
        }
        foreach ($counts as $count) {
            echo '<div><strong>' . esc_html($count['user']) . '</strong><span>' . esc_html((string) $count['count']) . ' művelet</span></div>';
        }
        echo '</div></section>';
        echo '<section class="akbo-card"><h2>Mai műveletek</h2><div class="akbo-table-wrap"><table class="akbo-table"><thead><tr><th>Idő</th><th>Munkatárs</th><th>Rendelés</th><th>Művelet</th><th>Állapot</th></tr></thead><tbody>';
        if ($activity === []) {
            echo '<tr><td colspan="5" class="akbo-empty">Ma még nincs rögzített Back Office művelet.</td></tr>';
        }
        foreach ($activity as $entry) {
            $orderId = absint($entry['order_id'] ?? 0);
            $from = FulfilmentWorkflow::labels()[FulfilmentWorkflow::state((string) ($entry['from'] ?? ''))];
            $to = FulfilmentWorkflow::labels()[FulfilmentWorkflow::state((string) ($entry['to'] ?? ''))];
            echo '<tr><td>' . esc_html(substr((string) ($entry['at'] ?? ''), 11, 5)) . '</td><td>' . esc_html((string) ($entry['user'] ?? 'Ismeretlen felhasználó')) . '</td><td>';
            echo $orderId > 0 ? '<a href="' . esc_url($this->url(['order' => $orderId])) . '">#' . esc_html((string) $orderId) . '</a>' : '—';
            echo '</td><td>' . esc_html(FulfilmentWorkflow::actions()[(string) ($entry['action'] ?? '')] ?? 'Ismeretlen művelet') . '</td><td>' . esc_html($from . ' → ' . $to) . '</td></tr>';
        }
        echo '</tbody></table></div></section></main>';
        $this->documentEnd();
    }

    /** @param array<string, scalar> $worklistContext */
    private function renderOrder(WC_Order $order, array $worklistContext): void
    {
        $state = $this->orders->state($order);
        $deliveryMode = $this->orders->deliveryMode($order);
        $this->documentStart('Rendelés #' . $order->get_order_number());
        echo '<main class="akbo-shell">';
        $backLink = '<a class="akbo-back" href="' . esc_url($this->url($worklistContext)) . '">← Vissza a rendelésekhez</a>';
        $printHelp = $deliveryMode === DeliveryMode::GLS ? 'Belső rendelési összesítő, nem GLS szállítási címke.' : 'Belső rendelési összesítő.';
        $printLink = '<div class="akbo-print-action"><a class="akbo-print-link" target="_blank" href="' . esc_url($this->url(['order' => $order->get_id(), 'print' => 1])) . '">Rendelési lap nyomtatása</a><small>' . esc_html($printHelp) . '</small></div>';
        $this->renderApplicationHeader('Rendelés #' . $order->get_order_number(), $backLink . $printLink);
        $this->notice();
        echo '<div class="akbo-status-line"><span class="akbo-badge akbo-state--' . esc_attr($state) . '">' . esc_html(FulfilmentWorkflow::labels()[$state]) . '</span><span>' . esc_html($this->paymentLabel($order)) . '</span><span>Átvétel módja: <strong>' . esc_html($this->orders->deliveryModeLabel($order)) . '</strong></span></div>';

        echo '<div class="akbo-layout"><div class="akbo-main">';
        $this->renderActions($order, $state, $deliveryMode, $worklistContext);
        $this->renderDevices($order);
        $this->renderCustomerAndOrder($order);
        if ($deliveryMode === DeliveryMode::GLS) {
            $this->renderGls($order);
        }
        $this->renderInternalNotes($order, $worklistContext);
        echo '</div><aside class="akbo-side">';
        $this->renderHistory($order);
        echo '</aside></div></main>';
        $this->documentEnd();
    }

    /** @param array<string, scalar> $worklistContext */
    private function renderActions(WC_Order $order, string $state, string $deliveryMode, array $worklistContext): void
    {
        echo '<section class="akbo-card"><h2>Következő lépés</h2>';
        $blockReason = $this->orders->fulfilmentBlockReason($order);
        if ($blockReason !== null) {
            echo '<p class="akbo-action-block">' . esc_html($blockReason) . '</p></section>';
            return;
        }

        $hasGlsLabel = $deliveryMode === DeliveryMode::GLS && $this->orders->hasGlsLabel($order);
        $primaryAction = FulfilmentWorkflow::primaryAction($state, $deliveryMode, $hasGlsLabel);
        if ($primaryAction === 'create_label' && ! $this->orders->canCreateGlsLabel()) {
            echo '<p class="akbo-action-block">' . esc_html($this->orders->glsReadinessMessage() ?? 'GLS kapcsolat nincs konfigurálva ebben a környezetben.') . ' A rendelés szállításra előkészítve marad.</p>';
        } elseif ($primaryAction !== null) {
            echo '<div class="akbo-actions">' . $this->actionForm($order, $primaryAction, FulfilmentWorkflow::actions()[$primaryAction], 'akbo-button akbo-button--primary', $worklistContext) . '</div>';
        } else {
            echo '<p class="akbo-help">Ehhez a rendeléshez jelenleg nincs további normál teljesítési lépés.</p>';
        }

        if ($primaryAction !== null && $state !== FulfilmentWorkflow::PROBLEM && ! in_array($state, [FulfilmentWorkflow::HANDED_TO_GLS, FulfilmentWorkflow::PICKED_UP], true)) {
            echo '<div class="akbo-actions akbo-actions--secondary">' . $this->actionForm($order, 'problem', FulfilmentWorkflow::actions()['problem'], 'akbo-button akbo-button--danger', $worklistContext) . '</div>';
        }
        if ($deliveryMode === DeliveryMode::GLS) {
            echo '<p class="akbo-help">A GLS címke létrehozása nem jelenti a csomag fizikai átadását.</p>';
        }
        echo '</section>';
    }

    private function renderDevices(WC_Order $order): void
    {
        echo '<section class="akbo-card"><h2>Termék / készülék</h2>';
        foreach ($this->orders->deviceItems($order) as $item) {
            echo '<article class="akbo-device"><h3>' . esc_html($item['name']) . ' <small>× ' . esc_html((string) $item['quantity']) . '</small></h3>';
            if ($item['details'] === []) {
                echo '<p class="akbo-empty">Nincs további Apple Klinika készülékadat.</p>';
            } else {
                echo '<dl class="akbo-details">';
                foreach ($item['details'] as $label => $value) {
                    echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
                }
                echo '</dl>';
            }
            echo '</article>';
        }
        echo '</section>';
    }

    private function renderCustomerAndOrder(WC_Order $order): void
    {
        echo '<section class="akbo-card akbo-two-columns"><div><h2>Ügyfél</h2><dl class="akbo-details"><div><dt>Név</dt><dd>' . esc_html($order->get_formatted_billing_full_name()) . '</dd></div><div><dt>E-mail</dt><dd>' . esc_html($order->get_billing_email()) . '</dd></div><div><dt>Telefon</dt><dd>' . esc_html($order->get_billing_phone()) . '</dd></div><div><dt>Számlázási cím</dt><dd>' . wp_kses_post(nl2br(esc_html($order->get_formatted_billing_address()))) . '</dd></div><div><dt>Szállítási cím</dt><dd>' . wp_kses_post(nl2br(esc_html($order->get_formatted_shipping_address()))) . '</dd></div></dl></div>';
        echo '<div><h2>Rendelés</h2><dl class="akbo-details"><div><dt>Létrehozva</dt><dd>' . esc_html(wc_format_datetime($order->get_date_created())) . '</dd></div><div><dt>Fizetési mód</dt><dd>' . esc_html($order->get_payment_method_title()) . '</dd></div><div><dt>Átvétel módja</dt><dd>' . esc_html($this->orders->deliveryModeLabel($order)) . '</dd></div><div><dt>WooCommerce szállítási metódus</dt><dd>' . esc_html($this->shippingMethod($order)) . '</dd></div><div><dt>Ügyfél megjegyzése</dt><dd>' . esc_html($order->get_customer_note() ?: '—') . '</dd></div></dl></div></section>';
    }

    private function renderGls(WC_Order $order): void
    {
        $hasLabel = $this->orders->hasGlsLabel($order);
        $labelUrl = $this->labelDownloadUrl($order);
        $tracking = $this->orders->trackingCodes($order);
        echo '<section class="akbo-card"><h2>GLS</h2><p><strong>Állapot:</strong> ' . esc_html($hasLabel ? 'Címke létrehozva' : 'Nincs címke') . '</p>';
        if ($hasLabel) {
            echo '<p><a class="akbo-button" target="_blank" href="' . esc_url($labelUrl) . '">Meglévő GLS címke nyomtatása</a></p>';
        } elseif (! $hasLabel) {
            $readiness = $this->orders->glsReadinessMessage();
            if ($readiness !== null) {
                echo '<p class="akbo-action-block">' . esc_html($readiness) . '</p>';
            } else {
                echo '<p class="akbo-help">A címke csak a fenti, sorrendben elérhető művelettel készülhet el. A címke létrehozása nem jelenti a fizikai GLS-átadást.</p>';
            }
        }
        if ($tracking !== []) {
            echo '<p><strong>Követési azonosító:</strong> ' . esc_html(implode(', ', $tracking)) . '</p>';
        }
        echo '</section>';
    }

    /** @param array<string, scalar> $worklistContext */
    private function renderInternalNotes(WC_Order $order, array $worklistContext): void
    {
        echo '<section class="akbo-card"><h2>Belső megjegyzések</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="akbo-note-form">';
        echo '<input type="hidden" name="action" value="appleklinika_backoffice_action"><input type="hidden" name="order_id" value="' . esc_attr((string) $order->get_id()) . '"><input type="hidden" name="backoffice_action" value="note">';
        $this->renderWorklistContextInputs($worklistContext);
        wp_nonce_field('appleklinika_backoffice_note_' . $order->get_id());
        echo '<label for="akbo-note">Csak munkatársaknak; az ügyfél nem látja.</label><textarea id="akbo-note" name="note" rows="3" required></textarea><button class="akbo-button" type="submit">Belső megjegyzés mentése</button></form>';
        $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'internal']);
        if ($notes !== []) {
            echo '<ul class="akbo-notes">';
            foreach ($notes as $note) {
                $content = (string) $note->content;
                if (! $this->orders->isManualInternalNote($content)) {
                    continue;
                }
                echo '<li><strong>' . esc_html($note->added_by) . '</strong><small>' . esc_html(wc_format_datetime($note->date_created)) . '</small><p>' . wp_kses_post(wpautop($this->orders->manualInternalNoteContent($content))) . '</p></li>';
            }
            echo '</ul>';
        }
        echo '</section>';
    }

    private function renderHistory(WC_Order $order): void
    {
        echo '<section class="akbo-card"><h2>Műveleti előzmények</h2><ol class="akbo-history">';
        $history = array_reverse($this->orders->history($order));
        if ($history === []) {
            echo '<li class="akbo-empty">Még nincs Back Office művelet.</li>';
        }
        foreach ($history as $entry) {
            echo '<li><strong>' . esc_html(FulfilmentWorkflow::actions()[$entry['action']] ?? $entry['action']) . '</strong><span>' . esc_html(FulfilmentWorkflow::labels()[$entry['from']] ?? $entry['from']) . ' → ' . esc_html(FulfilmentWorkflow::labels()[$entry['to']] ?? $entry['to']) . '</span><small>' . esc_html($entry['user'] . ' · ' . $entry['at']) . '</small></li>';
        }
        echo '</ol></section>';
    }

    private function renderPackingSheet(WC_Order $order): void
    {
        $deliveryMode = $this->orders->deliveryMode($order);
        $recipient = $deliveryMode === DeliveryMode::PICKUP ? $order->get_formatted_billing_full_name() : ($order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name());
        $address = $deliveryMode === DeliveryMode::PICKUP ? $order->get_formatted_billing_address() : ($order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address());
        $printHelp = $deliveryMode === DeliveryMode::GLS ? 'Belső rendelési összesítő, nem GLS szállítási címke.' : 'Belső rendelési összesítő.';
        $this->documentStart('Rendelési lap #' . $order->get_order_number(), true);
        echo '<main class="akbo-print"><header><h1>Apple Klinika — rendelési lap</h1><p>Rendelés: <strong>#' . esc_html($order->get_order_number()) . '</strong> · ' . esc_html(wc_format_datetime($order->get_date_created())) . '</p><p class="akbo-help">' . esc_html($printHelp) . '</p></header>';
        echo '<section><h2>Ügyfél és átvétel</h2><p><strong>' . esc_html($recipient) . '</strong><br>' . wp_kses_post(nl2br(esc_html($address))) . '<br>' . esc_html($order->get_billing_phone()) . '</p><p><strong>Átvétel módja:</strong> ' . esc_html($this->orders->deliveryModeLabel($order)) . '</p></section>';
        echo '<section><h2>Készülék</h2>';
        foreach ($this->orders->deviceItems($order) as $item) {
            echo '<h3>' . esc_html($item['name']) . ' × ' . esc_html((string) $item['quantity']) . '</h3><dl>';
            foreach ($item['details'] as $label => $value) {
                echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
            }
            echo '</dl>';
        }
        echo '</section><p class="akbo-print-controls"><button onclick="window.print()">Nyomtatás</button></p></main>';
        $this->documentEnd();
    }

    /** @param array<string, scalar> $worklistContext */
    private function actionForm(WC_Order $order, string $action, string $label, string $class, array $worklistContext): string
    {
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="appleklinika_backoffice_action">
            <input type="hidden" name="order_id" value="<?php echo esc_attr((string) $order->get_id()); ?>">
            <input type="hidden" name="backoffice_action" value="<?php echo esc_attr($action); ?>">
            <?php $this->renderWorklistContextInputs($worklistContext); ?>
            <?php wp_nonce_field('appleklinika_backoffice_' . $action . '_' . $order->get_id()); ?>
            <button class="<?php echo esc_attr($class); ?>" type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $source @return array<string, scalar> */
    public static function normalizeWorklistContext(array $source): array
    {
        $queue = strtolower((string) preg_replace('/[^a-z0-9_]/', '', (string) ($source['queue'] ?? '')));
        if ($queue !== '' && ! array_key_exists($queue, FulfilmentWorkflow::queueStates())) {
            $queue = '';
        }

        $searchType = strtolower((string) preg_replace('/[^a-z0-9_]/', '', (string) ($source['search_type'] ?? '')));
        if (! in_array($searchType, ['', 'order', 'customer', 'email', 'phone', 'device'], true)) {
            $searchType = '';
        }

        $search = trim(strip_tags((string) ($source['s'] ?? '')));
        $page = min(OrderQueueQuery::MAX_PAGE, max(1, (int) ($source['queue_page'] ?? 1)));
        $context = [];
        if ($queue !== '') {
            $context['queue'] = $queue;
        }
        if ($search !== '') {
            $context['s'] = $search;
        }
        if ($searchType !== '') {
            $context['search_type'] = $searchType;
        }
        if ($page > 1) {
            $context['queue_page'] = $page;
        }

        return $context;
    }

    /** @param array<string, mixed> $source @return array<string, scalar> */
    private function worklistContext(array $source): array
    {
        $context = self::normalizeWorklistContext([
            'queue' => isset($source['queue']) ? sanitize_key(wp_unslash($source['queue'])) : '',
            's' => isset($source['s']) ? sanitize_text_field(wp_unslash($source['s'])) : '',
            'search_type' => isset($source['search_type']) ? sanitize_key(wp_unslash($source['search_type'])) : '',
            'queue_page' => isset($source['queue_page']) ? absint(wp_unslash($source['queue_page'])) : 1,
        ]);

        return $context;
    }

    /** @param array<string, scalar> $worklistContext */
    private function renderWorklistContextInputs(array $worklistContext): void
    {
        foreach ($worklistContext as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
    }

    private function renderApplicationHeader(string $context, string $aside = ''): void
    {
        $user = wp_get_current_user();
        $isActivity = isset($_GET['view']) && sanitize_key(wp_unslash($_GET['view'])) === 'activity';
        echo '<header class="akbo-header"><div><p class="akbo-eyebrow">Apple Klinika</p><h1>Back Office</h1><p>' . esc_html($context) . '</p></div><div class="akbo-header-actions"><nav class="akbo-primary-nav" aria-label="Back Office"><a class="' . ($isActivity ? '' : 'is-active') . '" href="' . esc_url($this->url()) . '">Rendelések</a><a class="' . ($isActivity ? 'is-active' : '') . '" href="' . esc_url($this->url(['view' => 'activity'])) . '">Mai aktivitás</a></nav><div class="akbo-user"><span>' . esc_html($user->display_name ?: $user->user_login) . '</span><a class="akbo-link" href="' . esc_url(wp_logout_url(home_url('/backoffice/'))) . '">Kijelentkezés</a></div>' . $aside . '</div></header>';
    }

    private function attention(WC_Order $order, string $state): string
    {
        if ($state === FulfilmentWorkflow::PROBLEM) {
            return 'Probléma kezelése szükséges';
        }
        if (! $order->is_paid() && in_array($order->get_status(), ['pending', 'on-hold'], true)) {
            return 'Fizetés ellenőrzése szükséges';
        }
        return '';
    }

    private function paymentLabel(WC_Order $order): string
    {
        if ($order->is_paid()) {
            return 'Fizetés rendben';
        }

        return match ($order->get_status()) {
            'pending' => 'Fizetésre vár',
            'on-hold' => 'Ellenőrzés szükséges',
            'cancelled', 'failed', 'refunded' => 'Törölt',
            default => 'Ellenőrzés szükséges',
        };
    }

    private function shippingMethod(WC_Order $order): string
    {
        $methods = $order->get_shipping_methods();
        if ($methods === []) {
            return '—';
        }
        return implode(', ', array_map(static fn ($method): string => $method->get_name(), $methods));
    }

    private function labelDownloadUrl(WC_Order $order): string
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => 'appleklinika_backoffice_download_label',
                'order_id' => $order->get_id(),
            ], admin_url('admin-post.php')),
            'appleklinika_backoffice_download_label_' . $order->get_id()
        );
    }

    private function searchTypeLabel(string $searchType): string
    {
        return [
            'order' => 'rendelésszám',
            'customer' => 'ügyfélnév',
            'email' => 'e-mail',
            'phone' => 'telefon',
            'device' => 'IMEI / belső azonosító',
        ][$searchType] ?? 'automatikus';
    }

    private function renderPagination(string $queue, string $search, string $searchType, int $page, int $pageCount): void
    {
        if ($pageCount <= 1) {
            return;
        }

        $navigationPage = min($page, $pageCount);
        echo '<nav class="akbo-pagination" aria-label="Rendelési oldalak">';
        if ($navigationPage > 1) {
            echo '<a href="' . esc_url($this->queueUrl($queue, $search, $searchType, $navigationPage - 1)) . '">← Előző</a>';
        }
        foreach (range(max(1, $navigationPage - 2), min($pageCount, $navigationPage + 2)) as $number) {
            if ($number === $navigationPage) {
                echo '<span aria-current="page">' . esc_html((string) $number) . '</span>';
                continue;
            }
            echo '<a href="' . esc_url($this->queueUrl($queue, $search, $searchType, $number)) . '">' . esc_html((string) $number) . '</a>';
        }
        if ($navigationPage < $pageCount) {
            echo '<a href="' . esc_url($this->queueUrl($queue, $search, $searchType, $navigationPage + 1)) . '">Következő →</a>';
        }
        echo '</nav>';
    }

    private function queueUrl(string $queue, string $search, string $searchType, int $page): string
    {
        $arguments = ['queue_page' => $page];
        if ($queue !== '') {
            $arguments['queue'] = $queue;
        }
        if ($search !== '') {
            $arguments['s'] = $search;
        }
        if ($searchType !== '') {
            $arguments['search_type'] = $searchType;
        }

        return $this->url($arguments);
    }

    /** @param array<string, scalar> $worklistContext */
    private function orderUrl(int $orderId, array $worklistContext): string
    {
        return $this->url(array_merge($worklistContext, ['order' => $orderId]));
    }

    /** @param array<string, scalar> $args */
    private function url(array $args = []): string
    {
        $encodedArgs = array_map(static fn (string|int $value): string => rawurlencode((string) $value), $args);
        return add_query_arg($encodedArgs, home_url('/backoffice/'));
    }

    /** @param array<string, scalar> $worklistContext */
    private function redirect(int $orderId, string $notice, bool $error = false, array $worklistContext = []): void
    {
        $url = $this->url(array_merge($worklistContext, [
            'order' => $orderId,
            'notice' => $notice,
            'notice_type' => $error ? 'error' : 'success',
        ]));
        wp_safe_redirect($url);
        exit;
    }

    private function notice(): void
    {
        if (! isset($_GET['notice'])) {
            return;
        }
        $notice = sanitize_text_field(rawurldecode((string) wp_unslash($_GET['notice'])));
        $class = isset($_GET['notice_type']) && (string) wp_unslash($_GET['notice_type']) === 'error' ? 'akbo-notice--error' : 'akbo-notice--success';
        echo '<p class="akbo-notice ' . esc_attr($class) . '">' . esc_html($notice) . '</p>';
    }

    private function documentStart(string $title, bool $print = false): void
    {
        status_header(200);
        ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html($title); ?></title><?php wp_head(); ?><link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/backoffice.css', dirname(__DIR__, 2) . '/appleklinika-backoffice.php')); ?>"></head><body class="akbo-body<?php echo $print ? ' akbo-body--print' : ''; ?>"><?php
    }

    private function documentEnd(): void
    {
        wp_footer();
        echo '<script>document.querySelectorAll(".akbo-actions form, .akbo-note-form").forEach(function(form){form.addEventListener("submit",function(){var button=form.querySelector("button[type=submit]");if(button){button.disabled=true;button.textContent="Feldolgozás…";}});});</script></body></html>';
    }
}
