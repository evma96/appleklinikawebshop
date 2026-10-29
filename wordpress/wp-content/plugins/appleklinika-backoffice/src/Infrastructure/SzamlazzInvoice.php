<?php

declare(strict_types=1);

namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\OrderLifecycle;
use Appleklinika\BackOffice\Application\Port\InvoiceIssuer;
use WC_Order;

/** Adapter to the installed provider, never an HTTP client or shipping policy. */
final class SzamlazzInvoice implements InvoiceIssuer
{
    // Reuse the existing lifecycle attempt marker, including for older orders.
    private const ATTEMPT = '_appleklinika_lifecycle_invoice';

    /** Read-only projection of the installed payment-complete automation. */
    public function automationStatus(WC_Order $order): array
    {
        if (($message = WooArtifactReconciliation::message($order, 'invoice')) !== null) {
            $failure = $order->get_meta('_appleklinika_szamlazz_failure', true);
            if (is_array($failure) && !empty($failure['message'])) { $message .= ' ' . $this->safeMessage((string)$failure['message'], $this->selectedKey($order)); }
            return ['state'=>'reconciliation','label'=>'Automatikus számla — egyeztetés állapota','reason'=>$message];
        }
        $attempt = $order->get_meta(self::ATTEMPT, true);
        $attempt = is_array($attempt) ? $attempt : [];
        $state = (string) ($attempt['state'] ?? '');
        $failure = $order->get_meta('_appleklinika_szamlazz_failure', true);
        if ($state === '' && is_array($failure) && !empty($failure['message'])) { $state = 'failed'; }
        if (in_array($state, ['generating', 'failed', 'uncertain', 'document_missing'], true)) {
            $reason = match ($attempt['reason'] ?? '') {
                'provider_exception' => 'A szolgáltatói hívás megszakadt; az eredmény nem bizonyított.',
                'document_not_available' => 'Az automatikus művelet után nincs teljes számlaszám/PDF eredmény.',
                default => 'A korábbi kiállítás eredményét egyeztetni kell; új számla nem indítható.',
            };
            if (is_array($failure) && !empty($failure['message'])) {
                $reason .= ' ' . $this->safeMessage((string) $failure['message'], $this->selectedKey($order));
            } elseif (!empty($attempt['message'])) {
                $reason .= ' ' . $this->safeMessage((string) $attempt['message'], $this->selectedKey($order));
            }
            return ['state' => $state, 'label' => $state === 'generating' ? 'Automatikus számlázás folyamatban / eredményre vár' : 'Számlázási hiba — egyeztetés szükséges', 'reason' => $reason];
        }
        $block = $this->generationBlock($order);
        if (!(new OrderDocuments())->invoiceProviderActive()) {
            return ['state' => 'configuration', 'label' => 'Számlázási kapcsolat ellenőrzése szükséges', 'reason' => $block];
        }
        $key = $this->selectedKey($order);
        if ($key === '' || !$this->verifiedTestKey($key)) {
            return ['state' => 'configuration', 'label' => 'A kiválasztott számlázási fiók ellenőrzése szükséges', 'reason' => $block];
        }
        $skip = $this->automaticSkipReason($order);
        if ($skip !== null) {
            return ['state' => 'disabled', 'label' => 'Automatikus számlázás kikapcsolva ennél a rendelésnél', 'reason' => $skip];
        }
        if (!class_exists(SzamlazzAutomation::class) || !(new SzamlazzAutomation())->ready()) {
            return ['state' => 'configuration', 'label' => 'Automatikus számlázás nincs kész', 'reason' => $block ?? 'A fizetés utáni számlafolyamat nincs engedélyezve vagy a szolgáltatói számlaszabály nem megfelelő.'];
        }
        if (!$this->managed($order)) {
            return ['state' => 'unmanaged', 'label' => 'Még nincs elkészült számla', 'reason' => 'Ez a rendelés nem a jelenlegi fizetés utáni számlafolyamat kezelt rendelése. Nem jelzünk hozzá folyamatban lévő automatikus kiállítást.'];
        }
        if (in_array($order->get_status(), ['failed', 'cancelled', 'refunded', 'trash', 'checkout-draft'], true)) {
            return ['state' => 'stopped', 'label' => 'Automatikus számlázás nem indult', 'reason' => 'A rendelés jelenlegi fizetési/rendelési állapota nem indít fizetés utáni számlázást.'];
        }
        if (!$order->is_paid()) {
            return ['state' => 'waiting_payment', 'label' => 'Automatikus számlára vár — fizetés után', 'reason' => 'A sikeres fizetés indítja a webshop meglévő számlázását; nincs normál kézi teendő.'];
        }
        return ['state' => $state === 'blocked' ? 'blocked' : 'waiting', 'label' => $state === 'blocked' ? 'Automatikus számlázás korábban blokkolva' : 'Az automatikus számla még nem érkezett meg', 'reason' => $state === 'blocked' ? 'A számlafolyamat konfiguráció miatt nem küldött kiállítási kérést. Javítás után a helyreállítás újraindíthatja a meglévő folyamatot.' : 'A fizetés rögzítve van, de a számla még hiányzik. Az ütemezett egyeztetés pótolja az elmaradt jogosult műveletet. Nem szükséges kézi kiállítás.'];
    }

    /** These are the installed provider's explicit automatic opt-outs, not new billing rules. */
    private function automaticSkipReason(WC_Order $order): ?string
    {
        $provider = \WC_Szamlazz();
        if (!is_callable([$provider, 'get_option'])) { return null; }
        if ((float) $order->get_total() === 0.0 && $provider->get_option('disable_free_order', 'yes') === 'yes') {
            return 'A Számlázz.hu beállítása szerint a nulla összegű rendeléshez nem készül automatikus számla.';
        }
        if ($provider->check_payment_method_options($order->get_payment_method(), 'auto_disabled')) {
            return 'A Számlázz.hu beállításában ehhez a fizetési módhoz ki van kapcsolva az automatikus számlázás.';
        }
        foreach ($order->get_items() as $item) {
            if ($item->get_product() && $item->get_product()->get_meta('wc_szamlazz_disable_auto_invoice') === 'yes') {
                return 'A rendelés egyik termékénél ki van kapcsolva az automatikus Számlázz.hu számlázás.';
            }
        }
        return null;
    }

    /** Provider error hook: persist only safe diagnostics, never raw responses/XML. */
    public function rememberFailure(WC_Order $order, mixed $response): void
    {
        $code = is_array($response) && is_scalar($response['agent_error_code'] ?? null) ? (string) $response['agent_error_code'] : '';
        $message = $response === false ? 'A számlán nincs számlázható tétel.' : 'A szolgáltató nem adott sikeres számlaeredményt.';
        if (is_array($response) && is_scalar($response['agent_error'] ?? null) && (string) $response['agent_error'] !== '') {
            $message = urldecode((string) $response['agent_error']);
        } elseif (is_array($response) && is_scalar($response['http_error'] ?? null) && (string) $response['http_error'] !== '') {
            $message = 'Kapcsolati hiba: ' . (string) $response['http_error'];
        }
        $order->update_meta_data('_appleklinika_szamlazz_failure', [
            'code' => preg_match('/^\d{1,8}$/D', $code) ? $code : '',
            'message' => $this->safeMessage($message, $this->selectedKey($order)),
            'at' => current_time('mysql'),
        ]);
        $order->save_meta_data();
    }

    public function blockReason(int $id): ?string
    {
        $order = wc_get_order($id);
        return $order instanceof WC_Order ? $this->generationBlock($order) : 'A rendelés nem található.';
    }

    /** Read-only. Never invokes generation, preview hooks, or external services. */
    public function generationBlock(WC_Order $order): ?string
    {
        if ($order->get_meta('_wc_szamlazz_own')) {
            return 'A Számlázz.hu bővítményben a rendelés számlázása le van tiltva; ezt a Back Office nem kapcsolja vissza.';
        }
        if ($order->get_meta('_wc_szamlazz_invoice') || $order->get_meta('_wc_szamlazz_invoice_pdf')) {
            return 'Már van rögzített számla; új példány kiállítása nem indítható.';
        }
        if ($order->get_meta('_wc_szamlazz_receipt') || $order->get_meta('_wc_szamlazz_type_receipt')) {
            return 'A rendelés nyugta módban van. A számlakiállítás nem alakítja át nyugtáról számlára.';
        }
        if (! (new OrderDocuments())->invoiceProviderActive()) {
            return 'Kiállítás nem indítható: a Számlázz.hu bővítmény nem aktív.';
        }
        $provider = \WC_Szamlazz();
        if (! is_callable([$provider, 'generate_invoice']) || ! is_callable([$provider, 'get_szamlazz_agent_key'])) {
            return 'A telepített Számlázz.hu bővítmény szükséges kiállítási művelete nem érhető el.';
        }
        $key = $this->selectedKey($order);
        if ($key === '') {
            return 'Kiállítás nem indítható: ehhez a rendeléshez nincs beállított Számla Agent-kulcs.';
        }
        if (! $this->verifiedTestKey($key)) {
            return 'A rendeléshez kiválasztott számlázási fiók nem igazolt TEST-fiók, vagy nem TEST környezetben vagyunk. Kiállítás nem indítható.';
        }
        $attempt = $order->get_meta(self::ATTEMPT, true);
        if ((is_array($attempt) && in_array($attempt['state'] ?? '', ['generating', 'failed', 'uncertain'], true))
            || $order->get_meta('_appleklinika_szamlazz_failure', true)) {
            return 'A korábbi számlakiállítást egyeztetni kell a szolgáltatóval; új kérés nem indítható.'
                . (!empty($attempt['message']) ? ' ' . $this->safeMessage((string) $attempt['message'], $key) : '');
        }
        if ($this->managed($order)) {
            if (($skip = $this->automaticSkipReason($order)) !== null) { return $skip; }
            if (! $order->is_paid() || in_array($order->get_status(), ['cancelled', 'failed', 'refunded'], true)) {
                return 'Ezt a rendelést a fizetés utáni számlafolyamat kezeli; kiállítás csak igazolt fizetés után, nem megszakított vagy visszatérített rendelésnél indítható.';
            }
            if (! (new SzamlazzAutomation())->ready()) {
                return 'A fizetés utáni számlázási automatika nem kész: a licencet, az engedélyezést, az igazolt fiókot és az egyetlen fizetés utáni számlaszabályt ellenőrizni kell.';
            }
        }
        // The provider's manual action has no generic paid/shipping prerequisite.
        // Billing, tax, document and account-specific rules remain in that provider.
        return null;
    }

    /** Called by GenerateInvoice with the shared order lock held. */
    public function issue(int $id): void
    {
        $order = wc_get_order($id);
        $reason = $this->blockReason($id);
        if ($reason !== null || ! $order instanceof WC_Order) {
            throw new \InvalidArgumentException($reason ?? 'A rendelés nem található.');
        }
        if (get_current_user_id() < 1 || (!current_user_can('manage_appleklinika_backoffice') && !current_user_can('manage_options'))) {
            throw new \InvalidArgumentException('Nincs Back Office jogosultság.');
        }
        $key = $this->selectedKey($order);
        $managed = $this->managed($order);
        (new WooOrderBackOfficeRepository())->recordDocumentAccess($order, 'invoice_generation');
        if (! $managed) {
            $this->record($id, 'generating');
        }

        // The provider reads admin-form overrides from ambient POST. None are
        // accepted here: dates, payment, document type and account come from it.
        $post = $_POST;
        $_POST = [];
        $guard = function ($xml, $xmlOrder, $type) use ($id) {
            if (!$xmlOrder instanceof WC_Order || $xmlOrder->get_id() !== $id || $type !== 'invoice'
                || !is_object($xml) || !$this->verifiedTestKey((string) ($xml->beallitasok->szamlaagentkulcs ?? ''))) {
                throw new \RuntimeException('Unverified invoice account at the provider boundary.');
            }
            return $xml;
        };
        add_filter('wc_szamlazz_xml', $guard, PHP_INT_MAX, 3);
        $message = '';
        try {
            if ($managed) {
                // Preserve the installed lifecycle's guard, paid rule and retry policy.
                (new OrderLifecycle(new WooOrderLifecycleStore(), new WooOrderMutex(), new SzamlazzAutomation(), new WooCustomerMailer()))->paymentConfirmed($id);
            } else {
                $result = \WC_Szamlazz()->generate_invoice($id, 'invoice');
                if (is_array($result) && !empty($result['error'])) {
                    $message = $this->safeMessage(implode(' ', array_filter((array) ($result['messages'] ?? []), 'is_scalar')), $key);
                }
            }
        } catch (\Throwable) {
            // Never expose exception bodies/XML/customer data or retry an uncertain request.
            $message = 'A szolgáltatói művelet megszakadt; a kiállítás eredményét egyeztetni kell.';
        } finally {
            remove_filter('wc_szamlazz_xml', $guard, PHP_INT_MAX);
            $_POST = $post;
        }
        $fresh = wc_get_order($id);
        if ($fresh instanceof WC_Order && $fresh->get_meta('_wc_szamlazz_invoice')) {
            $available = (new OrderDocuments())->filePath($fresh, 'invoice') !== null;
            $this->record($id, $available ? 'ready' : 'document_missing');
            (new WooOrderBackOfficeRepository())->recordDocumentAccess(wc_get_order($id), 'invoice_generated');
            if (!$available) {
                throw new \InvalidArgumentException('A számla elkészült, de a PDF nem érhető el. Ne állíts ki új számlát; a dokumentumot kell ellenőrizni.');
            }
            return;
        }
        $message = $message ?: 'A szolgáltató nem rögzített számlaszámot. A kiállítás eredményét egyeztetni kell.';
        $this->record($id, 'uncertain', $message);
        if ($fresh instanceof WC_Order) {
            (new WooOrderBackOfficeRepository())->recordDocumentAccess(wc_get_order($id), 'invoice_generation_failed');
        }
        throw new \InvalidArgumentException($message . ' Új kiállítás egyeztetésig nem indítható.');
    }

    private function managed(WC_Order $order): bool
    {
        return class_exists(LifecycleConfiguration::class) && LifecycleConfiguration::manages($order);
    }

    private function selectedKey(WC_Order $order): string
    {
        if (!function_exists('WC_Szamlazz') || !is_callable([\WC_Szamlazz(), 'get_szamlazz_agent_key'])) { return ''; }
        $post = $_POST;
        $_POST = [];
        try { return trim((string) \WC_Szamlazz()->get_szamlazz_agent_key($order)); }
        finally { $_POST = $post; }
    }

    private function verifiedTestKey(string $key): bool
    {
        $home = strtolower((string) parse_url((string) get_option('home', ''), PHP_URL_HOST));
        if (!function_exists('wp_get_environment_type') || wp_get_environment_type() === 'production'
            || !in_array($home, ['teszt.appleklinika.com', 'localhost', '127.0.0.1'], true)) {
            return false;
        }
        // The same operator-verified account fingerprint used by the installed lifecycle.
        $pin = (string) get_option('appleklinika_szamlazz_test_agent_sha256', '');
        return $key !== '' && strlen($pin) === 64 && hash_equals($pin, hash('sha256', $key));
    }

    private function record(int $id, string $state, string $message = ''): void
    {
        $order = wc_get_order($id);
        if (!$order instanceof WC_Order) { throw new \RuntimeException('Order disappeared during invoicing.'); }
        $order->update_meta_data(self::ATTEMPT, ['state' => $state, 'reason' => $message !== '' ? 'provider_response' : '', 'message' => $message, 'updated_at' => current_time('mysql')]);
        $order->save_meta_data();
    }

    private function safeMessage(string $message, string $key): string
    {
        $message = str_replace(array_filter([$key, rawurlencode($key), htmlspecialchars($key, ENT_QUOTES)]), '[védett adat]', $message);
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($message)) ?? ''), 0, 600);
    }
}
