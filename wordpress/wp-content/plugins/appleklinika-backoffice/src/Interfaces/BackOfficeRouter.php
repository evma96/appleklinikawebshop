<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Interfaces;

use Appleklinika\BackOffice\Domain\DeliveryMode;
use Appleklinika\BackOffice\Domain\FulfilmentWorkflow;
use Appleklinika\BackOffice\Domain\OrderQueueQuery;
use Appleklinika\BackOffice\Infrastructure\WooOrderBackOfficeRepository;
use Appleklinika\BackOffice\Infrastructure\OrderDocuments;
use Appleklinika\BackOffice\Application\GenerateInvoice;
use Appleklinika\BackOffice\Infrastructure\SzamlazzInvoice;
use Appleklinika\BackOffice\Infrastructure\WooOrderMutex;
use InvalidArgumentException;
use WC_Order;

final class BackOfficeRouter
{
    public const CAPABILITY = 'manage_appleklinika_backoffice';
    private const QUERY_VAR = 'appleklinika_backoffice';

    public function __construct(private readonly WooOrderBackOfficeRepository $orders, private readonly ?\Appleklinika\BackOffice\Application\ChangeFulfilment $fulfilment = null)
    {
    }

    public function register(): void
    {
        add_action('init', [self::class, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'queryVars']);
        add_action('template_redirect', [$this, 'render']);
        add_action('admin_post_appleklinika_backoffice_action', [$this, 'handleAction']);
        add_action('admin_post_appleklinika_backoffice_download_mpl_label', [$this, 'handleMplLabelDownload']);
        add_action('admin_post_appleklinika_backoffice_download_label', [$this, 'handleLabelDownload']);
        add_action('admin_post_appleklinika_backoffice_download_invoice', [$this, 'handleInvoiceDownload']);
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

            if ($print && ($_GET['autoprint'] ?? null) === '1') {
                check_admin_referer('appleklinika_backoffice_print_sheet_' . $orderId);
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
            if (in_array($action, ['resolve_shipping','reconcile_shipping','confirm_manual_payment','recheck_barion'], true)) {
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { throw new InvalidArgumentException('A rendezés csak POST kéréssel végezhető.'); }
                $field = static fn(string $key): string => is_string($_POST[$key] ?? null) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
                $command = new \Appleklinika\BackOffice\Application\ResolveOrderReview(new \Appleklinika\BackOffice\Infrastructure\WooOrderReview($this->orders), new WooOrderMutex());
                $result = $command->execute($orderId, $action, $field('review_expected'), $field('shipping_choice'), $field('review_reason'), ($_POST['confirm_review'] ?? null) === '1', $field('review_operation'));
                $this->redirect($orderId, $result, false, $worklistContext);
            }
            if ($action === 'record_cash_pickup' && (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['confirm_cash_payment'] ?? null) !== '1')) {
                throw new InvalidArgumentException('A készpénzfizetés és személyes átadás rögzítéséhez kifejezett megerősítés szükséges.');
            }
            if (in_array($action, ['generate_invoice', 'create_label'], true) && ($_POST['confirm_recovery'] ?? null) !== '1') {
                throw new InvalidArgumentException('A kivételes szolgáltatói művelethez nyisd meg a helyreállítási lehetőségeket és erősítsd meg az ellenőrzést.');
            }
            // Invoice eligibility is independent of the fulfilment command gate.
            if ($action === 'generate_invoice') {
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    throw new InvalidArgumentException('Számlakiállítás csak POST kéréssel indítható.');
                }
                (new GenerateInvoice(new SzamlazzInvoice(), new WooOrderMutex()))->execute($orderId);
                $this->redirect($orderId, 'A számla elkészült; a Dokumentumok résznél megnyitható és nyomtatható.', false, $worklistContext);
            }
            if ($action === 'note') {
                $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
                $this->orders->addInternalNote($order, $note);
                $this->redirect($orderId, 'A belső megjegyzés elmentve.', false, $worklistContext);
            }

            if ($this->fulfilment === null || $action === 'correct') {
                throw new InvalidArgumentException('Ez a művelet itt nem érhető el.');
            }
            $expected = isset($_POST['expected_state']) ? sanitize_key(wp_unslash($_POST['expected_state'])) : '';
            $state = $this->fulfilment->execute($orderId, $action, get_current_user_id(), $expected);
            $notice = $action === 'create_label'
                ? 'GLS címke létrehozva. A rendelés állapota változatlanul: ' . FulfilmentWorkflow::labels()[$state] . '.'
                : 'Rendelés állapota frissítve: ' . $this->orders->operationalStateLabel(wc_get_order($orderId)) . '.';
            $this->redirect($orderId, $notice, false, $worklistContext);
        } catch (InvalidArgumentException $exception) {
            $this->redirect($orderId, $exception->getMessage(), true, $worklistContext);
        } catch (\Throwable $exception) {
            $this->redirect($orderId, 'A művelet nem hajtható végre biztonságosan.', true, $worklistContext);
        }
    }

    public function handleLabelDownload(): void
    {
        $this->downloadDocument('gls_label', 'label');
    }

    public function handleMplLabelDownload(): void
    {
        $this->downloadDocument('mpl_label', 'mpl_label');
    }

    public function handleInvoiceDownload(): void
    {
        $this->downloadDocument('invoice', 'invoice');
    }

    private function downloadDocument(string $document, string $nonceType): void
    {
        $this->requireAccess();
        $orderId = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
        $order = $orderId > 0 ? wc_get_order($orderId) : false;
        if (! $order instanceof WC_Order) {
            wp_die('A rendelés nem található.', 'Rendelés nem található', ['response' => 404]);
        }

        check_admin_referer('appleklinika_backoffice_download_' . $nonceType . '_' . $orderId);
        if ($document === 'gls_label' && $this->orders->deliveryMode($order) !== DeliveryMode::GLS) {
            wp_die('Ehhez a rendeléshez nem tartozik azonosított GLS szállítás.', 'Dokumentum nem található', ['response' => 404]);
        }
        $path = (new OrderDocuments())->filePath($order, $document);
        if ($path === null) {
            wp_die('A dokumentum nem érhető el.', 'Dokumentum nem található', ['response' => 404]);
        }

        if (($_GET['print'] ?? null) === '1' && in_array($document, ['invoice', 'gls_label'], true)) {
            // The wrapper has the same access/nonce/file checks as the PDF.
            // Only the subsequent PDF retrieval records document access.
            $this->renderDocumentPrint($order, $document);
            exit;
        }

        $this->orders->recordDocumentAccess($order, $document);

        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $document . '-' . $orderId . '.pdf"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
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
        $queueLabels = FulfilmentWorkflow::queueLabels();
        $this->renderApplicationHeader('Rendelések');
        $this->notice();

        echo '<section class="akbo-summary" aria-label="Nyitott rendelési összesítő">';
        foreach (['new', 'preparation', 'packing', 'ready_for_shipping', 'problem'] as $key) {
            $label = $queueLabels[$key];
            echo '<a class="' . ($queue === $key ? 'is-active' : '') . '" href="' . esc_url($this->queueUrl($key, '', '', 1)) . '"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) ($counts[$key] ?? 0)) . '</strong><small>Rendelések megnyitása →</small></a>';
        }
        echo '</section>';

        echo '<form class="akbo-search" method="get" action="' . esc_url(home_url('/backoffice/')) . '">';
        echo '<div class="akbo-filter-field"><label for="akbo-queue">Munkasor</label><select id="akbo-queue" name="queue" onchange="this.form.submit()">';
        foreach ($queueLabels as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($queue, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div><div class="akbo-search-field"><label for="akbo-search">Keresés a kiválasztott munkasorban</label>';
        echo '<input id="akbo-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="Rendelésszám, név, e-mail, telefon vagy IMEI"></div>';
        echo '<div class="akbo-type-field"><label for="akbo-search-type">Keresés típusa</label><select id="akbo-search-type" name="search_type">';
        foreach (['' => 'Automatikus', 'order' => 'Rendelésszám', 'customer' => 'Ügyfélnév', 'email' => 'E-mail', 'phone' => 'Telefon', 'device' => 'IMEI / belső azonosító'] as $type => $label) {
            echo '<option value="' . esc_attr($type) . '" ' . selected($requestedSearchType, $type, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></div><button type="submit">Keresés</button>';
        if ($search !== '') {
            echo '<p class="akbo-search-hint">Keresés: <strong>' . esc_html($search) . '</strong> · ' . esc_html($this->searchTypeLabel($searchType)) . ' <a href="' . esc_url($this->queueUrl($queue, '', '', 1)) . '">Keresés törlése</a></p>';
        }
        echo '</form>';

        echo '<section class="akbo-card akbo-worklist"><div class="akbo-list-heading"><h2>' . esc_html($queueLabels[$queue] ?? 'Rendelések') . '</h2><p class="akbo-result-count">' . esc_html((string) $total) . ' találat · ' . esc_html((string) $page) . '/' . esc_html((string) $pageCount) . '. oldal</p></div><div class="akbo-table-wrap"><table class="akbo-table akbo-order-table"><thead><tr><th>Rendelés</th><th>Ügyfél / átvétel</th><th>Termék / összeg</th><th>Állapot / fizetés</th><th>Következő teendő</th></tr></thead><tbody>';
        if ($orders === []) {
            echo '<tr><td colspan="5" class="akbo-empty">Ebben a munkasorban nincs a keresésnek megfelelő rendelés. Válassz másik munkasort vagy töröld a keresést.</td></tr>';
        }
        foreach ($orders as $order) {
            $primaryDevice = $this->orders->queuePrimaryItem($order);
            $state = $this->orders->state($order);
            $mode = $this->orders->deliveryMode($order);
            $block = $this->orders->fulfilmentBlockReason($order);
            $next = $this->orders->primaryAction($order);
            $closed = $order->has_status('completed') || in_array($state, [FulfilmentWorkflow::HANDED_TO_GLS, FulfilmentWorkflow::HANDED_TO_CARRIER, FulfilmentWorkflow::PICKED_UP], true);
            $attention = $closed ? '' : $this->attention($order, $state);
            $nextLabel = $closed ? 'Feldolgozás lezárva' : ($block !== null ? ($attention ?: 'Átvételi mód ellenőrzése') : ($next !== null ? FulfilmentWorkflow::actions()[$next] : 'Rendelés ellenőrzése'));
            if (! $closed && $block === null && $next === 'create_label') {
                $nextLabel = 'GLS feldolgozás ellenőrzése';
            }
            echo '<tr>';
            echo '<td data-label="Rendelés"><a href="' . esc_url($this->orderUrl($order->get_id(), $worklistContext)) . '">#' . esc_html($order->get_order_number()) . '</a><small>' . esc_html(wc_format_datetime($order->get_date_created())) . '</small></td>';
            echo '<td data-label="Ügyfél / átvétel"><strong>' . esc_html($order->get_formatted_billing_full_name() ?: 'Nincs név megadva') . '</strong><small>' . esc_html($order->get_billing_email()) . '</small><small class="akbo-delivery">' . esc_html($this->orders->deliveryModeLabel($order)) . '</small></td>';
            echo '<td data-label="Termék / összeg">' . esc_html($primaryDevice) . '<small class="akbo-amount">' . wp_kses_post($order->get_formatted_order_total()) . '</small></td>';
            $payment = \Appleklinika\BackOffice\Infrastructure\WooOrderPayment::describe($order, $mode);
            echo '<td data-label="Állapot / fizetés"><span class="akbo-badge akbo-state--' . esc_attr($state) . '">' . esc_html($this->orders->operationalStateLabel($order)) . '</span><small class="' . ($payment['review'] ? 'akbo-attention' : ($payment['state'] === 'paid' ? 'akbo-payment-ok' : '')) . '">' . esc_html($payment['label']) . '</small></td>';
            echo '<td data-label="Következő teendő"><span class="' . ($block !== null && ! $closed ? 'akbo-attention' : '') . '">' . esc_html($nextLabel) . '</span><small><a href="' . esc_url($this->orderUrl($order->get_id(), $worklistContext)) . '">Megnyitás →</a></small></td>';
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
            echo '</td><td>' . esc_html(FulfilmentWorkflow::activityLabels()[(string) ($entry['action'] ?? '')] ?? 'Ismeretlen művelet') . '</td><td>' . esc_html($from === $to ? $to : $from . ' → ' . $to) . '</td></tr>';
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
        echo '<a class="akbo-back" href="' . esc_url($this->url($worklistContext)) . '">← Vissza a rendelésekhez</a>';
        $this->renderApplicationHeader('Rendelés #' . $order->get_order_number());
        $this->notice();
        echo '<div class="akbo-status-line"><span class="akbo-badge akbo-state--' . esc_attr($state) . '">' . esc_html($this->orders->operationalStateLabel($order)) . '</span><span>' . esc_html($this->paymentLabel($order)) . '</span><span>' . esc_html($this->orders->deliveryModeLabel($order)) . '</span><strong>' . wp_kses_post($order->get_formatted_order_total()) . '</strong></div>';
        $this->renderProgress($order, $state, $deliveryMode);

        echo '<div class="akbo-layout"><div class="akbo-main">';
        $this->renderDevices($order);
        $this->renderCustomerAndOrder($order);
        $this->renderInternalNotes($order, $worklistContext);
        echo '</div><aside class="akbo-side">';
        $this->renderActions($order, $state, $deliveryMode, $worklistContext);
        $this->renderLastReview($order);
        $this->renderDocuments($order);
        $this->renderShipping($order);
        $this->renderHistory($order);
        echo '</aside></div></main>';
        $this->documentEnd();
    }

    private function renderProgress(WC_Order $order, string $state, string $deliveryMode): void
    {
        if (! DeliveryMode::isSupported($deliveryMode) || $order->has_status('completed')) {
            return;
        }
        $safeState = FulfilmentWorkflow::customerProgressState($state, $this->orders->history($order), $deliveryMode);
        $labels = FulfilmentWorkflow::customerProgressLabels($deliveryMode);
        if ($deliveryMode === DeliveryMode::GLS && $state === FulfilmentWorkflow::READY_FOR_SHIPPING) { $labels[$state] = $this->orders->operationalStateLabel($order); }
        $current = array_search($safeState, array_keys($labels), true);
        echo '<ol class="akbo-progress" aria-label="Rendelés feldolgozása">';
        foreach (array_values($labels) as $index => $label) {
            $class = $index < $current ? 'is-complete' : ($index === $current ? 'is-current' : '');
            echo '<li class="' . esc_attr($class) . '"' . ($index === $current ? ' aria-current="step"' : '') . '><span>' . ($index + 1) . '</span>' . esc_html($label) . '</li>';
        }
        echo '</ol>';
    }

    /** @param array<string, scalar> $worklistContext */
    private function renderActions(WC_Order $order, string $state, string $deliveryMode, array $worklistContext): void
    {
        echo '<section class="akbo-card akbo-next-action"><p class="akbo-eyebrow">Feldolgozás</p><h2>Következő lépés</h2>';
        echo '<p><strong>Jelenlegi állapot:</strong> ' . esc_html($this->orders->operationalStateLabel($order)) . '</p>';
        $pending = $order->get_meta('_appleklinika_shipping_review_operation', true);
        if (is_array($pending) && !empty($pending['id']) && ($pending['stage'] ?? '') !== 'complete') {
            $this->renderReview($order, $worklistContext);
            echo '</section>';
            return;
        }
        $this->renderManualShippingReview($order, $worklistContext);
        if ($order->has_status('completed') || in_array($state, [FulfilmentWorkflow::DELIVERED, FulfilmentWorkflow::PICKED_UP], true)) {
            echo '<p class="akbo-completion">A rendelés feldolgozása lezárult.</p></section>';
            return;
        }
        $blockReason = $this->orders->fulfilmentBlockReason($order);
        if ($blockReason !== null) {
            echo '<p class="akbo-action-block">' . esc_html($blockReason) . '</p>';
            $this->renderReview($order, $worklistContext);
            echo '</section>';
            return;
        }

        $hasGlsLabel = $deliveryMode === DeliveryMode::GLS && $this->orders->hasGlsLabel($order);
        $primaryAction = $this->orders->primaryAction($order);
        $documentsBlock = $state === FulfilmentWorkflow::READY_FOR_SHIPPING ? $this->orders->shippingDocumentsBlock($order) : null;
        if ($documentsBlock !== null) {
            echo '<p class="akbo-action-block">' . esc_html($documentsBlock) . '</p>';
        } elseif ($primaryAction === 'create_mpl_label' && \Appleklinika\BackOffice\Infrastructure\MplCarrier::readiness() !== null) {
            echo '<p class="akbo-action-block">' . esc_html(\Appleklinika\BackOffice\Infrastructure\MplCarrier::readiness()) . '</p>';
        } elseif ($primaryAction === 'create_label') {
            echo '<p class="akbo-help">A csomag előkészítve. A GLS feldolgozás állapotát a Szállítás / átvétel rész mutatja; a címkekészítés nem normál kézi munkalépés.</p>';
        } elseif ($primaryAction !== null) {
            echo '<p class="akbo-help">' . esc_html(match ($primaryAction) {
                'accept_cash_pickup' => 'Ellenőrizd és tedd félre a rendelés készülékeit. Ez a művelet elküldi a rendelés elfogadását; a fizetés továbbra is átvételkor esedékes.',
                'record_cash_pickup' => 'Csak a tényleges készpénzfizetés és személyes átadás után rögzítsd. A fizetéshez egyszer készül számla.',
                'start' => \Appleklinika\BackOffice\Infrastructure\CashPickup::matches($order)
                    ? 'Vedd munkába és készítsd elő a rendelést. A rendszer ellenőrzi a meglévő készletfoglalást és megőrzi az elfogadási értesítést. A fizetés csak a személyes átadáskor esedékes.'
                    : 'Vedd munkába a rendelést, és ellenőrizd a megrendelt készüléket.',
                'start_packing' => 'Az ellenőrzött készülék és a tartozékok csomagolása következik.',
                'packing_completed' => $deliveryMode === DeliveryMode::GLS ? 'A csomag lezárásával, igazolt fizetés mellett automatikusan elkészül a GLS címke. Futárátadás még nem történik.' : 'A csomag lezárása után jelöld szállításra késznek.',
                'prepare_pickup' => 'Készítsd elő a rendelést a személyes átvételre.',
                'picked_up' => 'Csak a vásárlónak történő tényleges átadás után rögzítsd az átvételt.',
                'delivered' => 'Csak a kézbesítés visszaigazolása után zárd le a teljesítést.',
                'handed_to_carrier', 'handed_to_gls' => 'Csak a futárnak történő tényleges átadás után rögzítsd az átadást.',
                'resume' => 'A probléma rendezése után a rendelés visszakerül az előkészítéshez.',
                default => 'A következő művelet a rendelés aktuális állapotához kapcsolódik.',
            }) . '</p>';
            echo '<div class="akbo-actions">' . $this->actionForm($order, $primaryAction, FulfilmentWorkflow::actions()[$primaryAction], 'akbo-button akbo-button--primary', $worklistContext) . '</div>';
        } else {
            echo '<p class="akbo-help">Ehhez a rendeléshez jelenleg nincs további normál teljesítési lépés.</p>';
        }

        if ($primaryAction !== null && $state !== FulfilmentWorkflow::PROBLEM && ! in_array($state, [FulfilmentWorkflow::HANDED_TO_GLS, FulfilmentWorkflow::HANDED_TO_CARRIER, FulfilmentWorkflow::PICKED_UP], true)) {
            echo '<div class="akbo-actions akbo-actions--secondary">' . $this->actionForm($order, 'problem', FulfilmentWorkflow::actions()['problem'], 'akbo-button akbo-button--danger', $worklistContext) . '</div>';
        }
        if ($deliveryMode === DeliveryMode::GLS) {
            echo '<p class="akbo-help">A GLS címke létrehozása nem jelenti a csomag fizikai átadását.</p>';
        }
        echo '</section>';
    }

    /** Read model only; rendering never starts a provider operation. */
    private function renderReview(WC_Order $order, array $context): void
    {
        $review = (new \Appleklinika\BackOffice\Infrastructure\WooOrderReview($this->orders))->review($order->get_id());
        if ($review['pending'] !== null) { $this->renderPendingShippingReview($order, $review, $context); return; }
        if ($review['facts']['mode'] === DeliveryMode::UNKNOWN) {
            echo '<h3>Hiányzó / hibás átvételi mód javítása</h3>';
            if ($review['shipping_block'] !== null) { echo '<p class="akbo-help">'.esc_html($review['shipping_block']).'</p>'; }
            else {
                echo '<p class="akbo-help">A WooCommerce szállítási tételét javítja. A szállítási díj, az adók és a végösszeg változatlan marad; különböző díjazást előbb egyeztetni kell.</p>';
                $this->reviewForm($order, 'resolve_shipping', 'Átvételi mód mentése', $review, $context);
            }
        }
        $payment = \Appleklinika\BackOffice\Infrastructure\WooOrderPayment::describe($order, $review['facts']['mode']);
        if ($payment['review'] || $review['payment_action'] !== '') {
            $optional = !$payment['review'] && $review['payment_action'] === 'recheck_barion';
            if ($optional) { echo '<details><summary>Barion állapot újraellenőrzése</summary>'; }
            else { echo '<h3>Fizetés rendezése</h3>'; }
            echo '<p class="akbo-help">'.esc_html($review['payment_message']).'</p>';
            if ($review['payment_action'] !== '') { $this->reviewForm($order, $review['payment_action'], $review['payment_action'] === 'recheck_barion' ? 'Barion állapot újraellenőrzése' : 'Fizetés beérkezésének igazolása', $review, $context); }
            if ($optional) { echo '</details>'; }
        }
    }

    private function renderManualShippingReview(WC_Order $order, array $context): void
    {
        if (!array_filter($this->orders->history($order), static fn(array $entry): bool => ($entry['action'] ?? '') === 'resolve_shipping')) { return; }
        $review = (new \Appleklinika\BackOffice\Infrastructure\WooOrderReview($this->orders))->review($order->get_id());
        if ($review['pending'] !== null || $review['facts']['mode'] === DeliveryMode::UNKNOWN || !$review['facts']['manual_shipping']) { return; }
        echo '<details><summary>Korábbi kézi átvételi mód javítása</summary>';
        if ($review['shipping_block'] !== null) { echo '<p class="akbo-help">'.esc_html($review['shipping_block']).'</p>'; }
        else { $this->reviewForm($order, 'resolve_shipping', 'Új, indokolt javítás mentése', $review, $context); }
        echo '</details>';
    }

    private function renderPendingShippingReview(WC_Order $order, array $review, array $context): void
    {
        $pending = $review['pending'];
        echo '<h3>Félbemaradt javítás egyeztetése</h3><p class="akbo-action-block">A döntés előzetesen rögzítve van, de a teljes mentés és naplózás nincs lezárva. A szállítási adat már megváltozhatott. Új döntés helyett ugyanennek a műveletnek az eredményét ellenőrizzük.</p>';
        echo '<p>Jelenlegi adat: '.esc_html(\Appleklinika\BackOffice\Infrastructure\WooOrderReview::describeFacts($review['facts'], 'resolve_shipping')).'</p><p>Tervezett mód: '.esc_html($pending['target']['label']).'</p><p>'.esc_html($pending['user'].' · '.$pending['at']).'</p><p>Belső indoklás: '.esc_html($pending['reason']).'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="akbo-review-form"><input type="hidden" name="action" value="appleklinika_backoffice_action"><input type="hidden" name="order_id" value="'.esc_attr((string)$order->get_id()).'"><input type="hidden" name="backoffice_action" value="reconcile_shipping"><input type="hidden" name="review_operation" value="'.esc_attr($pending['id']).'">';
        $this->renderWorklistContextInputs($context);
        wp_nonce_field('appleklinika_backoffice_reconcile_shipping_'.$order->get_id());
        echo '<label><input type="checkbox" name="confirm_review" value="1" required> A fenti, korábban rögzített döntés egyeztetését kérem. Ha az adat még érintetlen és a feltételek változatlanok, a javítás befejezhető; ha már módosult, csak a hiányzó naplózás készül el. Eltérésnél nincs felülírás.</label><button type="submit" class="akbo-button">Mentés és napló egyeztetése</button></form>';
    }

    private function reviewForm(WC_Order $order, string $action, string $label, array $review, array $context): void
    {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="akbo-review-form">';
        echo '<input type="hidden" name="action" value="appleklinika_backoffice_action"><input type="hidden" name="order_id" value="'.esc_attr((string)$order->get_id()).'"><input type="hidden" name="backoffice_action" value="'.esc_attr($action).'"><input type="hidden" name="review_expected" value="'.esc_attr($review['fingerprint']).'">';
        $this->renderWorklistContextInputs($context);
        wp_nonce_field('appleklinika_backoffice_'.$action.'_'.$order->get_id());
        if ($action === 'resolve_shipping') {
            echo '<label for="akbo-review-method">Ellenőrzött átvételi mód</label><select id="akbo-review-method" name="shipping_choice" required><option value="">Válassz az ellenőrzés alapján…</option>';
            foreach ($review['choices'] as $key=>$choice) { echo '<option value="'.esc_attr($key).'">'.esc_html($choice['label']).'</option>'; }
            echo '</select>';
            echo '<div id="akbo-review-preview" aria-live="polite"><p class="akbo-help" data-review-hint>Válassz ellenőrzött módot. Az alábbi előnézetek az aktuális rendelésből és az engedélyezett módokból készülnek.</p>';
            foreach ($review['shipping_previews'] as $key => $lines) {
                echo '<div data-review-choice="'.esc_attr($key).'"><strong>Mentés után:</strong><ul>';
                foreach ($lines as $line) { echo '<li>'.esc_html($line).'</li>'; }
                echo '</ul></div>';
            }
            echo '</div><p class="akbo-help">Ez új, indokolt javítás, nem visszavonás: minden korábbi döntés megmarad az előzményekben. Csak az ügyféllel egyeztetett módot válaszd. Meglévő küldemény, átadás vagy előrehaladott előkészítés esetén külön rendelésadminisztrációs egyeztetés szükséges; ez a művelet nem von vissza számlát vagy küldeményt.</p>';
        } else {
            echo '<p class="akbo-help"><strong>A művelet hatása:</strong> '.esc_html($action === 'recheck_barion'
                ? 'A meglévő Barion-fizetés állapotát kérdezzük le; új terhelés nem indul. Csak egyező, sikeres szolgáltatói fizetés esetén folytatódhat a normál fizetésrögzítés. Egyébként az eredmény az előzményekben marad, a fizetés nem lesz igazolt.'
                : 'A teljes jóváírt összeget ehhez a rendeléshez kell egyeztetned. Ez nem banki ellenőrzés: a megerősítésed rögzíti a fizetést a WooCommerce-ben.').'</p>';
            echo '<p class="akbo-help">Sikeres fizetésrögzítéskor a rendelés állapota, fizetési időpontja és készletkezelése változhat; a meglévő automatikus számlázás és ügyfélértesítés elindulhat. Az átvételi mód nem változik, GLS-címke ettől nem készül. Nincs egykattintásos visszavonás: téves igazolást külön kell egyeztetni.</p>';
        }
        echo '<label for="akbo-review-'.$action.'">Belső indoklás (csak munkatársaknak)</label><textarea id="akbo-review-'.$action.'" name="review_reason" rows="2" minlength="5" maxlength="500" aria-describedby="akbo-review-help-'.$action.'" required></textarea>';
        echo '<p class="akbo-help" id="akbo-review-help-'.$action.'">A Back Office előzményeibe és a WooCommerce belső megjegyzésébe kerül. Nem ügyfélüzenet, nem küldjük a GLS-nek, Barionnak vagy a számlázónak. Írd le, mit és miből ellenőriztél: például telefonos ügyfélmegerősítés, eredeti rendelési e-mail vagy belső rendelési nyilvántartás. Ne adj meg jelszót vagy bankkártyaadatot.</p>';
        echo '<label><input type="checkbox" name="confirm_review" value="1" required> '.esc_html($action === 'confirm_manual_payment' ? 'A teljes összeg tényleges beérkezését ellenőriztem. Ez fizetést rögzít és elindíthatja a meglévő számlázási automatizmust.' : ($action === 'resolve_shipping' ? 'Az átvételi módot és a változatlan díjakat ellenőriztem; a WooCommerce rendelés javítását megerősítem.' : 'A valódi Barion-állapot újraellenőrzését kérem; sikeres fizetésnél a meglévő fizetési folyamat folytatódhat.')).'</label><button type="submit" class="akbo-button">'.esc_html($label).'</button></form>';
    }

    private function renderLastReview(WC_Order $order): void
    {
        foreach (array_reverse($this->orders->history($order)) as $entry) {
            if (!is_array($entry['review'] ?? null)) { continue; }
            $review = $entry['review'];
            $reader = new \Appleklinika\BackOffice\Infrastructure\WooOrderReview($this->orders);
            echo '<section class="akbo-card akbo-review-result"><h2>Legutóbbi ellenőrzés eredménye</h2><p>'.esc_html((string)($review['result'] ?? '')).'</p><dl class="akbo-details">';
            foreach (['before'=>'Előtte','after'=>'Utána'] as $key => $label) {
                echo '<div><dt>'.esc_html($label).'</dt><dd>'.esc_html($reader::describeFacts($review[$key] ?? [], $entry['action'])).'</dd></div>';
            }
            echo '<div><dt>Munkatárs / időpont</dt><dd>'.esc_html($entry['user'].' · '.$entry['at']).'</dd></div><div><dt>Belső indoklás</dt><dd>'.esc_html((string)($review['reason'] ?? '')).'</dd></div></dl><p><strong>Jelenlegi következő lépés:</strong> '.esc_html($reader->nextStep($order)).'</p><p class="akbo-help">A mentett esemény a Műveleti előzményekben később is visszanézhető. A következő lépés a rendelés jelenlegi állapotát mutatja.</p></section>';
            return;
        }
    }

    private function renderDevices(WC_Order $order): void
    {
        echo '<section class="akbo-card"><h2>Termék / készülék</h2>';
        $items = $this->orders->deviceItems($order);
        if ($items === []) {
            echo '<p class="akbo-empty">A rendeléshez nincs rögzített terméktétel.</p>';
        }
        foreach ($items as $item) {
            echo '<article class="akbo-device"><h3>' . esc_html($item['name']) . ' <small>× ' . esc_html((string) $item['quantity']) . '</small></h3>';
            echo '<p class="akbo-line-total">' . wp_kses_post($item['total_html']) . '</p>';
            if ($item['details'] === []) {
                echo '<p class="akbo-empty">Nincs további Apple Klinika készülékadat.</p>';
            } else {
                echo '<dl class="akbo-details">';
                foreach ($item['details'] as $label => $value) {
                    $source = $item['detail_sources'][$label] ?? '';
                    $sourceLabel = $label === 'Belső azonosító / IMEI' ? ($source === 'order' ? 'Rendeléskor mentett adat' : 'Aktuális termékadat') : '';
                    echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . ($sourceLabel !== '' ? '<small class="akbo-data-source">' . esc_html($sourceLabel) . '</small>' : '') . '</dd></div>';
                }
                echo '</dl>';
            }
            echo '</article>';
        }
        if ($items !== []) {
            echo '<p class="akbo-help">A rendeléskor mentett adatok az elsődlegesek; a hiányzó készülékadatok a termék aktuális nyilvántartásából érkeznek.</p>';
        }
        echo '</section>';
    }

    private function renderCustomerAndOrder(WC_Order $order): void
    {
        echo '<section class="akbo-card"><h2>Ügyfél és átvétel</h2><dl class="akbo-details"><div><dt>Név</dt><dd>' . esc_html($order->get_formatted_billing_full_name() ?: '—') . '</dd></div><div><dt>E-mail</dt><dd>' . esc_html($order->get_billing_email() ?: '—') . '</dd></div><div><dt>Telefon</dt><dd>' . esc_html($order->get_billing_phone() ?: '—') . '</dd></div><div><dt>Átvétel módja</dt><dd>' . esc_html($this->orders->deliveryModeLabel($order)) . '</dd></div><div><dt>Számlázási cím</dt><dd>' . self::addressHtml($order->get_formatted_billing_address()) . '</dd></div>';
        if ($this->orders->deliveryMode($order) !== DeliveryMode::PICKUP) {
            echo '<div><dt>Szállítási cím</dt><dd>' . self::addressHtml($order->get_formatted_shipping_address()) . '</dd></div>';
        }
        echo '</dl>';
        if ($order->get_customer_note() !== '') {
            echo '<div class="akbo-customer-note"><strong>Ügyfél megjegyzése</strong><p>' . nl2br(esc_html($order->get_customer_note())) . '</p></div>';
        }
        echo '</section><section class="akbo-card"><h2>Fizetés és rendelési adatok</h2><dl class="akbo-details"><div><dt>Rendelés összege</dt><dd>' . wp_kses_post($order->get_formatted_order_total()) . '</dd></div><div><dt>Fizetési mód</dt><dd>' . esc_html($order->get_payment_method_title() ?: 'Nincs megadva') . '</dd></div><div><dt>Fizetés</dt><dd>' . esc_html($this->paymentLabel($order)) . '</dd></div><div><dt>Rendelés állapota</dt><dd>' . esc_html(wc_get_order_status_name($order->get_status())) . '</dd></div><div><dt>Beérkezett</dt><dd>' . esc_html(wc_format_datetime($order->get_date_created(), 'Y. m. d. H:i')) . '</dd></div><div><dt>Szállítási mód</dt><dd>' . esc_html($this->shippingMethod($order)) . '</dd></div></dl></section>';
    }

    public static function addressHtml(string $address): string
    {
        // WooCommerce already returns escaped text separated by <br/> tags.
        return trim($address) === '' ? '—' : wp_kses($address, ['br' => []]);
    }

    private function renderDocuments(WC_Order $order): void
    {
        $sheet = $this->url(['order' => $order->get_id(), 'print' => 1]);
        $sheetPrint = wp_nonce_url(add_query_arg(['autoprint' => 1], $sheet), 'appleklinika_backoffice_print_sheet_' . $order->get_id());
        echo '<section class="akbo-card akbo-documents" aria-labelledby="akbo-documents-title"><h2 id="akbo-documents-title">Dokumentumok</h2>';
        echo '<div class="akbo-document"><h3>Rendelési lap</h3><p class="akbo-help">Elérhető · Belső rendelési összesítő, nem számla vagy futárcímke.</p>';
        $this->renderDocumentLinks('Rendelési lap', $sheet, $sheetPrint);
        echo '</div>';
        $this->renderInvoice($order);
        if ($this->orders->deliveryMode($order) === DeliveryMode::GLS) {
            $label = (new OrderDocuments())->glsLabel($order);
            echo '<div class="akbo-document"><h3>GLS címke</h3><p>' . esc_html($label['available'] ? 'Elérhető · PDF megnyitható és nyomtatható.' : ($label['recorded'] ? 'Címke rögzítve, de a PDF jelenleg nem érhető el.' : 'Még nincs elkészült GLS címke.')) . '</p>';
            if ($label['tracking_codes'] !== []) {
                echo '<p class="akbo-document-number">Csomagszám: ' . esc_html(implode(', ', $label['tracking_codes'])) . '</p>';
            }
            if ($label['available']) {
                $url = $this->labelDownloadUrl($order);
                $this->renderDocumentLinks('GLS címke', $url, $this->labelDownloadUrl($order, true));
            }
            echo '</div>';
        }
        echo '</section>';
    }

    private function renderDocumentLinks(string $label, string $openUrl, string $printUrl): void
    {
        echo '<div class="akbo-document-actions"><a class="akbo-button akbo-button--secondary" target="_blank" rel="noopener" aria-label="' . esc_attr($label . ' megnyitása') . '" href="' . esc_url($openUrl) . '">Megnyitás</a>';
        echo '<a class="akbo-button" target="_blank" rel="noopener" aria-label="' . esc_attr($label . ' nyomtatása') . '" href="' . esc_url($printUrl) . '">Nyomtatás</a></div>';
    }

    private function renderInvoice(WC_Order $order): void
    {
        $invoice = (new OrderDocuments())->invoice($order);
        echo '<div class="akbo-document"><h3>Számlázz.hu számla</h3>';
        echo '<p><strong>' . esc_html($invoice['state_label']) . '</strong></p>';
        if ($invoice['automation_reason'] !== '') {
            echo '<p class="akbo-help">' . esc_html($invoice['automation_reason']) . '</p>';
        }
        if ($invoice['number'] !== '') {
            echo '<p class="akbo-document-number">' . esc_html($invoice['number']) . '</p>';
        }
        if ($invoice['manual']) {
            echo '<p>Kézzel rögzített dokumentum a számlázóbővítményben.</p>';
        }
        if ($invoice['paid'] !== '') {
            echo '<p>Számlázz.hu szerinti kiegyenlítés: ' . esc_html($invoice['paid'] === '1' ? 'kiegyenlített, dátum nélkül' : $invoice['paid']) . '</p>';
        } elseif ($invoice['recorded']) {
            echo '<p>A számlázóbővítményben nincs rögzített kiegyenlítés.</p>';
        }
        foreach (['void_number' => 'Sztornóbizonylat', 'receipt_number' => 'Nyugtaszám', 'disabled_reason' => 'Számlázás letiltásának oka'] as $key => $label) {
            if ($invoice[$key] !== '') {
                echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($invoice[$key]) . '</p>';
            }
        }
        if ($invoice['available']) {
            $url = $this->invoiceDownloadUrl($order);
            $this->renderDocumentLinks('Számla', $url, $this->invoiceDownloadUrl($order, true));
        } else {
            if (! $invoice['provider_active'] || $invoice['recorded']) {
                echo '<p class="akbo-empty">' . esc_html(! $invoice['provider_active'] ? 'A Számlázz.hu kapcsolat jelenleg nem aktív.' : 'A számla rögzítve van, de a PDF-fájl nem érhető el.') . '</p>';
            }
        }
        if (!$invoice['recorded']) {
            echo '<details class="akbo-recovery"><summary>További lehetőségek — számlázás helyreállítása</summary>';
            echo '<p class="akbo-help">Kivételes művelet, nem a normál feldolgozás része. Előbb ellenőrizd az automatikus folyamatot és hogy nem készült már bizonylat a szolgáltatónál.</p>';
            if ($invoice['generation_block'] === null) {
                echo $this->actionForm($order, 'generate_invoice', 'Számlázás helyreállítása', 'akbo-button akbo-button--secondary', $this->worklistContext($_GET));
            } else {
                echo '<p class="akbo-help">' . esc_html($invoice['generation_block']) . '</p>';
            }
            echo '</details>';
        }
        echo '</div>';
    }

    private function renderShipping(WC_Order $order): void
    {
        $mode = $this->orders->deliveryMode($order);
        $methods = $this->orders->shippingMethods($order);
        echo '<section class="akbo-card"><h2>Szállítás / átvétel</h2>';
        foreach ($methods as $method) {
            echo '<p><strong>' . esc_html($method['name'] ?: 'Névtelen szállítási tétel') . '</strong><br><small>WooCommerce metódus: ' . esc_html($method['method_id'] ?: 'hiányzik') . ($method['instance_id'] !== '' ? ' · példány: ' . esc_html($method['instance_id']) : '') . '</small></p>';
        }
        if ($mode === DeliveryMode::UNKNOWN) {
            echo '<p class="akbo-action-block">' . esc_html($methods === []
                ? 'Nincs WooCommerce szállítási tétel a rendelésen. Az átvételi mód nem állapítható meg; ellenőrzés szükséges.'
                : 'A WooCommerce szállítási metódus hiányos, nem támogatott vagy többféle átvételt jelöl. Ellenőrzés szükséges.') . '</p>';
            echo '<p class="akbo-help">A korábbi feldolgozási állapot önmagában nem azonosítja a szállítót.</p>';
        } elseif ($mode === DeliveryMode::PICKUP) {
            echo '<p>Személyes átvétel az üzletben. Futárcímke és csomagkövetés nem szükséges.</p>';
        } elseif ($mode === DeliveryMode::MPL) {
            echo '<h3>MPL címke és követés</h3><p>' . esc_html(\Appleklinika\BackOffice\Infrastructure\MplCarrier::hasLabel($order) ? 'Címke rögzítve. Átadás előtt zárd le a feladójegyzéket a WooCommerce MPL szállítólevelek felületén.' : (\Appleklinika\BackOffice\Infrastructure\MplCarrier::readiness() ?? 'Még nincs elkészült címke.')) . '</p>';
            if ((new OrderDocuments())->filePath($order, 'mpl_label') !== null) {
                $url = wp_nonce_url(add_query_arg(['action'=>'appleklinika_backoffice_download_mpl_label','order_id'=>$order->get_id()],admin_url('admin-post.php')), 'appleklinika_backoffice_download_mpl_label_'.$order->get_id());
                echo '<p><a class="akbo-button" target="_blank" rel="noopener" href="'.esc_url($url).'">MPL címke megnyitása / nyomtatása</a></p>';
            }
            foreach ((new OrderDocuments())->trackingLinks($order) as $link) { echo '<p><a target="_blank" rel="noopener" href="'.esc_url($link['url']).'">MPL: '.esc_html($link['code']).'</a></p>'; }
            echo '<p class="akbo-help">A címke és a feladójegyzék elkészítése nem jelenti a tényleges postai átadást.</p>';
        } else {
            $this->renderGls($order);
        }
        echo '</section>';
    }

    private function renderGls(WC_Order $order): void
    {
        $document = (new OrderDocuments())->glsLabel($order);
        $hasLabel = $document['available'];
        $tracking = $document['tracking_codes'];
        echo '<h3>GLS címke és követés</h3><p>' . esc_html($hasLabel ? 'A címke nyomtatásra kész.' : ($document['recorded'] ? 'Címke rögzítve, de a PDF nem érhető el.' : 'Még nincs elkészült címke.')) . '</p>';
        if (! $hasLabel) {
            if ($document['failure'] !== '') {
                echo '<p class="akbo-action-block">' . esc_html($document['failure']) . '</p>';
            }
            $readiness = $this->orders->glsReadinessMessage();
            if ($readiness !== null) {
                echo '<p class="akbo-action-block">' . esc_html($readiness) . '</p>';
            } else {
                echo '<p class="akbo-help">' . esc_html($document['recorded'] || $document['parcel_ids'] !== [] || $tracking !== [] ? 'Már van szolgáltatói adat; új címke helyett a meglévő PDF-et kell helyreállítani.' : (new \Appleklinika\BackOffice\Infrastructure\GlsAutomation($this->orders))->status($order)) . '</p>';
            }
            $recoveryBlock = $this->orders->glsRecoveryBlock($order);
            if ($this->orders->state($order) === FulfilmentWorkflow::READY_FOR_SHIPPING) {
                echo '<details class="akbo-recovery"><summary>További lehetőségek — GLS helyreállítása</summary>';
                echo '<p class="akbo-help">Csak ellenőrzött kivételhez. A címke nem jelenti a csomag fizikai átadását.</p>';
                echo $recoveryBlock === null
                    ? $this->actionForm($order, 'create_label', 'GLS feldolgozás helyreállítása', 'akbo-button akbo-button--secondary', $this->worklistContext($_GET))
                    : '<p class="akbo-action-block">' . esc_html($recoveryBlock) . '</p>';
                echo '</details>';
            }
        }
        if ($tracking !== []) {
            echo '<p><strong>GLS csomagszám:</strong> ' . esc_html(implode(', ', $tracking)) . '</p>';
        } else {
            echo '<p>Még nincs rögzített GLS csomagszám.</p>';
        }
        if ($document['parcel_ids'] !== []) {
            echo '<p><strong>GLS belső parcel ID:</strong> ' . esc_html(implode(', ', $document['parcel_ids'])) . '</p>';
        }
        foreach ((new OrderDocuments())->trackingLinks($order) as $link) {
            echo '<p><a class="akbo-link" target="_blank" rel="noopener" href="' . esc_url($link['url']) . '">Csomag követése: ' . esc_html($link['code']) . ' ↗</a></p>';
        }
        echo '<p class="akbo-help">A követési link a GLS oldalát nyitja meg; itt nem történik élő státuszlekérdezés.</p>';
    }

    /** @param array<string, scalar> $worklistContext */
    private function renderInternalNotes(WC_Order $order, array $worklistContext): void
    {
        echo '<section class="akbo-card"><h2>Belső megjegyzések</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="akbo-note-form">';
        echo '<input type="hidden" name="action" value="appleklinika_backoffice_action"><input type="hidden" name="order_id" value="' . esc_attr((string) $order->get_id()) . '"><input type="hidden" name="backoffice_action" value="note">';
        $this->renderWorklistContextInputs($worklistContext);
        wp_nonce_field('appleklinika_backoffice_note_' . $order->get_id());
        echo '<label for="akbo-note">Csak munkatársaknak; az ügyfél nem látja.</label><textarea id="akbo-note" name="note" rows="3" required></textarea><button class="akbo-button" type="submit">Belső megjegyzés mentése</button></form>';
        $notes = array_filter(wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'internal']), fn ($note): bool => $this->orders->isManualInternalNote((string) $note->content));
        if ($notes === []) {
            echo '<p class="akbo-empty">Még nincs munkatársi megjegyzés.</p>';
        }
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
        echo '<section class="akbo-card"><h2>Műveleti előzmények</h2><ol class="akbo-history" tabindex="0" aria-label="Rendelés műveleti előzményei">';
        $history = array_reverse($this->orders->history($order));
        if ($history === []) {
            echo '<li class="akbo-empty">Még nincs Back Office művelet.</li>';
        }
        foreach ($history as $entry) {
            $from = FulfilmentWorkflow::labels()[$entry['from']] ?? $entry['from'];
            $to = FulfilmentWorkflow::labels()[$entry['to']] ?? $entry['to'];
            echo '<li><strong>' . esc_html(FulfilmentWorkflow::activityLabels()[$entry['action']] ?? $entry['action']) . '</strong><span>' . esc_html($from === $to ? $to : $from . ' → ' . $to) . '</span><small>' . esc_html($entry['user'] . ' · ' . $entry['at']) . '</small>';
            if (is_array($entry['review'] ?? null)) {
                $review = $entry['review'];
                echo '<p>'.esc_html((string)($review['result'] ?? '')).'</p><p>Belső indoklás: '.esc_html((string)($review['reason'] ?? '')).'</p>';
                echo '<p>'.esc_html(\Appleklinika\BackOffice\Infrastructure\WooOrderReview::describeFacts($review['before'] ?? [], $entry['action']).' → '.\Appleklinika\BackOffice\Infrastructure\WooOrderReview::describeFacts($review['after'] ?? [], $entry['action'])).'</p>';
                if (isset($review['requested_at'])) { echo '<small>Döntés: '.esc_html($review['requested_at']).' · Naplóbejegyzést rögzítő munkatárs azonosítója: '.esc_html((string)$review['finalized_by']).'</small>'; }
            }
            echo '</li>';
        }
        echo '</ol></section>';
    }

    private function renderPackingSheet(WC_Order $order): void
    {
        $deliveryMode = $this->orders->deliveryMode($order);
        $address = $deliveryMode === DeliveryMode::PICKUP ? $order->get_formatted_billing_address() : ($order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address());
        $printHelp = $deliveryMode === DeliveryMode::GLS ? 'Belső rendelési összesítő, nem GLS szállítási címke.' : 'Belső rendelési összesítő.';
        $this->documentStart('Rendelési lap #' . $order->get_order_number(), true);
        echo '<main class="akbo-print"><header><h1>Apple Klinika — rendelési lap</h1><p>Rendelés: <strong>#' . esc_html($order->get_order_number()) . '</strong> · ' . esc_html(wc_format_datetime($order->get_date_created())) . '</p><p class="akbo-help">' . esc_html($printHelp) . '</p></header>';
        echo '<section><h2>Ügyfél és átvétel</h2><p>' . self::addressHtml($address) . '<br>' . esc_html($order->get_billing_phone()) . '</p><p><strong>Átvétel módja:</strong> ' . esc_html($this->orders->deliveryModeLabel($order)) . '</p></section>';
        echo '<section><h2>Készülék</h2>';
        foreach ($this->orders->deviceItems($order) as $item) {
            echo '<h3>' . esc_html($item['name']) . ' × ' . esc_html((string) $item['quantity']) . '</h3><dl>';
            foreach ($item['details'] as $label => $value) {
                echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
            }
            echo '</dl>';
        }
        echo '</section><p class="akbo-print-controls"><button class="akbo-button" onclick="window.print()">Nyomtatás</button></p></main>';
        if (($_GET['autoprint'] ?? null) === '1') {
            echo '<div data-akbo-print="sheet"></div>';
            $this->documentPrintScript();
        }
        $this->documentEnd();
    }

    private function renderDocumentPrint(WC_Order $order, string $document): void
    {
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        $label = $document === 'invoice' ? 'Számla' : 'GLS címke';
        $url = $document === 'invoice' ? $this->invoiceDownloadUrl($order) : $this->labelDownloadUrl($order);
        $this->documentStart($label . ' nyomtatása — #' . $order->get_order_number());
        echo '<main class="akbo-shell akbo-document-print" data-akbo-print="pdf" data-document-url="' . esc_url($url) . '">';
        echo '<div class="akbo-print-controls"><a class="akbo-back" href="' . esc_url($this->orderUrl($order->get_id(), $this->worklistContext($_GET))) . '">← Vissza a rendeléshez</a>';
        echo '<h1>' . esc_html($label) . ' · #' . esc_html($order->get_order_number()) . '</h1><div class="akbo-document-actions"><button class="akbo-button" data-document-print-button disabled>Nyomtatás</button>';
        echo '<a class="akbo-button akbo-button--secondary" target="_blank" rel="noopener" href="' . esc_url($url) . '">PDF megnyitása</a></div>';
        echo '<p role="status" data-document-print-status>Dokumentum betöltése…</p><p class="akbo-help">Ha a böngésző nem nyitja meg automatikusan a nyomtatást, kattints a Nyomtatás gombra. Ha a beágyazott PDF nyomtatása nem támogatott, válaszd a PDF megnyitása műveletet, majd a PDF-megjelenítő nyomtatóikonját vagy a Ctrl/Cmd+P billentyűket.</p>';
        echo '<noscript>A nyomtatási nézethez JavaScript szükséges. A PDF megnyitása hivatkozás JavaScript nélkül is használható.</noscript></div>';
        echo '<iframe class="akbo-document-frame" title="' . esc_attr($label . ' PDF') . '" data-document-frame hidden></iframe></main>';
        $this->documentPrintScript();
        $this->documentEnd();
    }

    private function documentPrintScript(): void
    {
        $url = add_query_arg('ver', (string) filemtime(dirname(__DIR__, 2) . '/assets/document-print.js'), plugins_url('assets/document-print.js', dirname(__DIR__, 2) . '/appleklinika-backoffice.php'));
        echo '<script defer src="' . esc_url($url) . '"></script>';
    }

    private function invoiceDownloadUrl(WC_Order $order, bool $print = false): string
    {
        $args = ['action' => 'appleklinika_backoffice_download_invoice', 'order_id' => $order->get_id()];
        if ($print) { $args['print'] = 1; }
        return wp_nonce_url(add_query_arg($args, admin_url('admin-post.php')), 'appleklinika_backoffice_download_invoice_' . $order->get_id());
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
            <input type="hidden" name="expected_state" value="<?php echo esc_attr($this->orders->state($order)); ?>">
            <?php $this->renderWorklistContextInputs($worklistContext); ?>
            <?php wp_nonce_field('appleklinika_backoffice_' . $action . '_' . $order->get_id()); ?>
            <?php if ($action === 'record_cash_pickup'): ?>
                <label class="akbo-help"><input type="checkbox" name="confirm_cash_payment" value="1" required> A teljes összeget készpénzben átvettem, és a rendelést személyesen átadtam.</label>
            <?php endif; ?>
            <?php if (in_array($action, ['generate_invoice', 'create_label'], true)): ?>
                <label class="akbo-help"><input type="checkbox" name="confirm_recovery" value="1" required> Ellenőriztem: nincs már kiállított vagy folyamatban lévő szolgáltatói dokumentum.</label>
            <?php endif; ?>
            <button class="<?php echo esc_attr($class); ?>" type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $source @return array<string, scalar> */
    public static function normalizeWorklistContext(array $source): array
    {
        $queue = strtolower((string) preg_replace('/[^a-z0-9_]/', '', (string) ($source['queue'] ?? '')));
        if (! array_key_exists($queue, FulfilmentWorkflow::queueLabels())) {
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
        echo '<header class="akbo-header"><div><p class="akbo-eyebrow">Apple Klinika <span>Back Office</span></p><h1>' . esc_html($context) . '</h1></div><div class="akbo-header-actions"><nav class="akbo-primary-nav" aria-label="Back Office"><a class="' . ($isActivity ? '' : 'is-active') . '" href="' . esc_url($this->url()) . '">Rendelések</a><a class="' . ($isActivity ? 'is-active' : '') . '" href="' . esc_url($this->url(['view' => 'activity'])) . '">Mai aktivitás</a></nav><div class="akbo-user"><span>' . esc_html($user->display_name ?: $user->user_login) . '</span><a class="akbo-link" href="' . esc_url(wp_logout_url(home_url('/backoffice/'))) . '">Kijelentkezés</a></div>' . $aside . '</div></header>';
    }

    private function attention(WC_Order $order, string $state): string
    {
        if ($state === FulfilmentWorkflow::PROBLEM) {
            return 'Probléma kezelése szükséges';
        }
        if ($this->orders->deliveryMode($order) === DeliveryMode::UNKNOWN) { return 'Átvételi mód ellenőrzése'; }
        $payment = \Appleklinika\BackOffice\Infrastructure\WooOrderPayment::describe($order, $this->orders->deliveryMode($order));
        if ($payment['block'] !== null) { return $payment['label']; }
        return $state === FulfilmentWorkflow::READY_FOR_SHIPPING && $this->orders->shippingDocumentsBlock($order) !== null ? 'Dokumentumokra vár' : '';
    }

    private function paymentLabel(WC_Order $order): string
    {
        return \Appleklinika\BackOffice\Infrastructure\WooOrderPayment::describe($order, $this->orders->deliveryMode($order))['label'];
    }

    private function shippingMethod(WC_Order $order): string
    {
        $methods = $order->get_shipping_methods();
        if ($methods === []) {
            return '—';
        }
        return implode(', ', array_map(static fn ($method): string => $method->get_name(), $methods));
    }

    private function labelDownloadUrl(WC_Order $order, bool $print = false): string
    {
        $args = ['action' => 'appleklinika_backoffice_download_label', 'order_id' => $order->get_id()];
        if ($print) { $args['print'] = 1; }
        return wp_nonce_url(
            add_query_arg($args, admin_url('admin-post.php')),
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
        $styleUrl = add_query_arg('ver', (string) filemtime(dirname(__DIR__, 2) . '/assets/backoffice.css'), plugins_url('assets/backoffice.css', dirname(__DIR__, 2) . '/appleklinika-backoffice.php'));
        ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html($title); ?></title><?php wp_head(); ?><link rel="stylesheet" href="<?php echo esc_url($styleUrl); ?>"></head><body class="akbo-body<?php echo $print ? ' akbo-body--print' : ''; ?>"><?php
    }

    private function documentEnd(): void
    {
        wp_footer();
        $script = add_query_arg('ver', (string)filemtime(dirname(__DIR__, 2).'/assets/review-preview.js'), plugins_url('assets/review-preview.js', dirname(__DIR__, 2).'/appleklinika-backoffice.php'));
        echo '<script src="'.esc_url($script).'" defer></script>';
        echo '<script>document.querySelectorAll(".akbo-actions form, .akbo-note-form").forEach(function(form){form.addEventListener("submit",function(){var button=form.querySelector("button[type=submit]");if(button){button.disabled=true;button.textContent="Feldolgozás…";}});});</script></body></html>';
    }
}
