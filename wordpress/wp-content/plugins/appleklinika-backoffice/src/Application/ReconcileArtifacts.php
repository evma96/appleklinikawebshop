<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Application;

use Appleklinika\BackOffice\Application\Port\{ArtifactReconciliationStore, OrderMutex};

/** Reuses provider operations; never infers success from a queue job or HTTP response. */
final class ReconcileArtifacts
{
    public function __construct(private readonly ArtifactReconciliationStore $store, private readonly OrderMutex $mutex) {}

    public function execute(int $id, int $now): void
    {
        $this->mutex->synchronized($id, function () use ($id, $now): void {
            foreach (['invoice', 'gls'] as $kind) { $this->reconcile($id, $kind, $now); }
        });
    }

    private function reconcile(int $id, string $kind, int $now): void
    {
        $facts = $this->store->facts($id, $kind);
        $record = $this->store->journal($id, $kind);
        if ($facts['complete']) {
            // Also repairs the audit projection after successful provider persistence.
            if (($record !== [] || ($facts['eligible'] && in_array($facts['attempt'], ['generating','failed','uncertain','document_missing'], true)))
                && ($record['state'] ?? '') !== 'done') {
                $this->store->audit($id, $kind, 'done', $kind . ':done');
                $this->store->save($id, $kind, array_merge($record, ['state'=>'done','at'=>$now]));
            }
            return;
        }
        if (!$facts['eligible']) { return; }
        if ($facts['evidence'] || in_array($facts['attempt'], ['generating','attempting','failed','uncertain','ready','document_missing'], true)) {
            $this->stop($id, $kind, $record, 'reconciliation', $now);
            return;
        }
        // A journal interrupted before the adapter reports a known no-document result
        // is deliberately not replayed. No cross-provider exactly-once claim is made.
        if (($record['state'] ?? '') === 'running' && !in_array($facts['attempt'], ['blocked','retryable'], true)) {
            $this->stop($id, $kind, $record, 'reconciliation', $now);
            return;
        }
        if ($facts['block'] !== null) {
            if (($record['state'] ?? '') !== 'blocked' || ($record['reason'] ?? '') !== $facts['block']) {
                $this->store->save($id, $kind, array_merge($record, ['state'=>'blocked','reason'=>$facts['block'],'at'=>$now]));
            }
            return; // Configuration checks may repeat; no provider request is sent.
        }
        if (in_array($record['state'] ?? '', ['reconciliation','exhausted'], true)) { return; }
        if ((int)($record['next_at'] ?? 0) > $now) { return; }
        if ((int)($record['attempts'] ?? 0) >= 3) {
            $this->stop($id, $kind, $record, 'exhausted', $now);
            return;
        }
        $record = ['state'=>'running','attempts'=>(int)($record['attempts'] ?? 0)+1,'at'=>$now,'next_at'=>$now+300];
        $this->store->save($id, $kind, $record); // Verified durable evidence BEFORE invoking a provider.
        try { $this->store->generate($id, $kind); } catch (\Throwable) { /* Inspect authoritative outcome below. */ }
        $fresh = $this->store->facts($id, $kind);
        if ($fresh['complete']) {
            $this->store->audit($id, $kind, 'done', $kind . ':done');
            $this->store->save($id, $kind, array_merge($record, ['state'=>'done']));
        } elseif (!$fresh['evidence'] && in_array($fresh['attempt'], ['blocked','retryable'], true)) {
            $record['state'] = $record['attempts'] >= 3 ? 'exhausted' : 'retry_wait';
            $record['next_at'] = $now + 300 * $record['attempts'];
            $this->store->save($id, $kind, $record);
            $this->store->audit($id, $kind, $record['state'], $kind . ':retry:' . $record['attempts']);
        } else {
            $this->stop($id, $kind, $record, 'reconciliation', $now);
        }
    }

    private function stop(int $id, string $kind, array $record, string $state, int $now): void
    {
        $this->store->audit($id, $kind, $state, $kind . ':' . $state);
        if (($record['state'] ?? '') !== $state) {
            $this->store->save($id, $kind, array_merge($record, ['state'=>$state,'at'=>$now]));
        }
    }
}
