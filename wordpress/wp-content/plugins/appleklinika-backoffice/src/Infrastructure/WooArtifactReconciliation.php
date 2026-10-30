<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

use Appleklinika\BackOffice\Application\{ReconcileArtifacts, OrderLifecycle};
use Appleklinika\BackOffice\Application\Port\ArtifactReconciliationStore;
use Appleklinika\BackOffice\Domain\{DeliveryMode, FulfilmentWorkflow};

/** Bounded Action Scheduler adapter. Rendering never invokes this worker. */
final class WooArtifactReconciliation implements ArtifactReconciliationStore
{
    public const SCAN = 'appleklinika_artifacts_scan';
    public const WORK = 'appleklinika_artifacts_reconcile';
    public const META = '_appleklinika_artifact_reconciliation';
    private const GROUP = 'appleklinika-lifecycle';

    public static function enabled(): bool
    {
        return (ProductionReadiness::production() && ProductionReadiness::allowed()) || in_array(wp_get_environment_type(), ['local','staging'], true)
            && in_array(strtolower((string)parse_url((string)get_option('home'), PHP_URL_HOST)), ['localhost','127.0.0.1','teszt.appleklinika.com'], true);
    }

    public function schedule(): void
    {
        if (!self::enabled() || !function_exists('as_schedule_recurring_action')) { return; }
        if (!as_has_scheduled_action(self::SCAN, [], self::GROUP)) {
            as_schedule_recurring_action(time()+60, 300, self::SCAN, [], self::GROUP, true);
        }
    }

    public function enqueue(int $id): void
    {
        if (self::enabled() && $id > 0 && function_exists('as_enqueue_async_action')) {
            // AS's unique insert is hook/group-wide in the installed version,
            // not order-argument-wide. Serialize the exact-argument precheck.
            (new WooOrderMutex())->synchronized(0, static function () use ($id): void {
                if (!as_has_scheduled_action(self::WORK, [$id], self::GROUP)) {
                    as_enqueue_async_action(self::WORK, [$id], self::GROUP, false);
                }
            });
        }
    }

    public function scan(): void
    {
        if (!self::enabled()) { return; }
        (new WooOrderMutex())->synchronized(0, function (): void {
            $page = max(1, (int)get_option('appleklinika_artifacts_scan_page', 1));
            $result = wc_get_orders(['type'=>'shop_order','status'=>['pending','on-hold','processing','completed'],
                'limit'=>25,'page'=>$page,'paginate'=>true,'return'=>'ids','orderby'=>'ID','order'=>'ASC']);
            foreach ($result->orders as $id) { $this->enqueue((int)$id); }
            update_option('appleklinika_artifacts_scan_page', $page < (int)$result->max_num_pages ? $page+1 : 1, false);
        });
    }

    public function dispatch(int $id): void
    {
        if (!self::enabled() || !doing_action(self::WORK)) { return; }
        (new ReconcileArtifacts($this, new WooOrderMutex()))->execute($id, time());
    }

    public function facts(int $id, string $kind): array
    {
        $o = wc_get_order($id);
        $empty = ['eligible'=>false,'complete'=>false,'evidence'=>false,'attempt'=>'','block'=>null];
        if (!$o instanceof \WC_Order) { return $empty; }
        $docs = new OrderDocuments(); $repo = new WooOrderBackOfficeRepository();
        $key = $kind === 'invoice' ? '_appleklinika_lifecycle_invoice' : '_appleklinika_gls_attempt';
        $attempt = $o->get_meta($key, true);
        $facts = $empty; $facts['attempt'] = is_array($attempt) ? (string)($attempt['state'] ?? '') : '';
        if ($kind === 'invoice') {
            $facts['evidence'] = (bool)($o->get_meta('_wc_szamlazz_invoice',true) || $o->get_meta('_wc_szamlazz_invoice_pdf',true));
            $facts['complete'] = (bool)$o->get_meta('_wc_szamlazz_invoice',true) && $docs->filePath($o,'invoice') !== null;
            $facts['eligible'] = class_exists(LifecycleConfiguration::class) && LifecycleConfiguration::manages($o)
                && $o->is_paid() && $o->get_date_paid() !== null && in_array($o->get_status(), ['processing','completed'], true);
            $facts['block'] = (new SzamlazzInvoice())->generationBlock($o);
        } else {
            foreach (['_gls_print_label','_gls_parcel_ids','_gls_tracking_codes','_gls_tracking_code'] as $meta) { $facts['evidence'] = $facts['evidence'] || (bool)$o->get_meta($meta,true); }
            $facts['complete'] = $docs->filePath($o,'gls_label') !== null && $repo->trackingCodes($o) !== [];
            $facts['eligible'] = $repo->deliveryMode($o) === DeliveryMode::GLS && $repo->state($o) === FulfilmentWorkflow::READY_FOR_SHIPPING
                && $o->is_paid() && $o->get_date_paid() !== null && $repo->fulfilmentBlockReason($o) === null;
            $facts['block'] = $repo->glsReadinessMessage();
        }
        return $facts;
    }

    public function journal(int $id, string $kind): array
    {
        $r = wc_get_order($id)->get_meta(self::META,true);
        return is_array($r) && is_array($r[$kind] ?? null) ? $r[$kind] : [];
    }

    public function save(int $id, string $kind, array $record): void
    {
        $o = wc_get_order($id); $all = $o->get_meta(self::META,true); $all = is_array($all) ? $all : [];
        $all[$kind] = $record; $o->update_meta_data(self::META,$all); $o->save_meta_data();
        if ($this->journal($id,$kind) !== $record) { throw new \RuntimeException('Artifact reconciliation journal was not persisted; no new provider call is safe.'); }
    }

    public function generate(int $id, string $kind): void
    {
        if (!self::enabled() || !doing_action(self::WORK)) { throw new \RuntimeException('Authorized environment scheduled worker required.'); }
        $post = $_POST;
        $_POST = []; // Provider options come from persisted order/configuration, never an ambient queue POST.
        try {
            if ($kind === 'invoice') {
                (new OrderLifecycle(new WooOrderLifecycleStore(),new WooOrderMutex(),new SzamlazzAutomation(),new WooCustomerMailer()))->paymentConfirmed($id);
            } else { (new GlsAutomation(new WooOrderBackOfficeRepository()))->runScheduled($id); }
        } finally { $_POST = $post; }
    }

    public function audit(int $id, string $kind, string $result, string $key): void
    {
        if ($kind === 'invoice' && $result === 'done') {
            // Recover only from persisted order/provider data, never ambient POST.
            $post = $_POST; $_POST = [];
            try {
                (new OrderLifecycle(new WooOrderLifecycleStore(), new WooOrderMutex(), new SzamlazzAutomation(), new WooCustomerMailer()))->paymentConfirmed($id);
                do_action('wc_szamlazz_document_created', ['order_id'=>$id, 'document_type'=>'invoice']);
            } finally { $_POST = $post; }
        }
        (new WooOrderBackOfficeRepository())->recordAutomation($id, $kind, $result, $key);
    }

    public static function message(\WC_Order $order, string $kind): ?string
    {
        $all = $order->get_meta(self::META,true); $r = is_array($all) ? ($all[$kind] ?? []) : [];
        return match ($r['state'] ?? '') {
            'running' => time()-(int)($r['at']??0) < 600 ? 'Automatikus dokumentum-egyeztetés folyamatban.' : 'Az automatikus kérés megszakadhatott. Eredményegyeztetés szükséges; vak újrapróbálás nem indul.',
            'retry_wait' => 'A szolgáltató igazoltan nem készített dokumentumot. Korlátozott automatikus újrapróbálásra vár.',
            'exhausted' => 'Az automatikus újrapróbálások elfogytak. A szolgáltatói hibát javítani és az eredményt egyeztetni kell.',
            'reconciliation' => 'A korábbi kérés vagy a hiányos dokumentum eredményét egyeztetni kell. Új dokumentum nem készül automatikusan.',
            'blocked' => 'Automatikus egyeztetés blokkolva: ' . (string)($r['reason']??'A szolgáltatói feltételek hiányosak.'),
            default => null,
        };
    }
}
