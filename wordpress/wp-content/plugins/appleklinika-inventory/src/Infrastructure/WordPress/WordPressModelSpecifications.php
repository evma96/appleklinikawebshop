<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Infrastructure\WordPress;

use Appleklinika\Inventory\Application\ModelSpecification\ModelSpecifications;
use Appleklinika\Inventory\Domain\ModelSpecification\ModelIdentity;

final class WordPressModelSpecifications implements ModelSpecifications
{
    public const OPTION = 'appleklinika_official_specs_by_model';
    private const VERSION_OPTION = 'appleklinika_model_specs_bootstrap_version';

    /** Versioned provisioning of the existing registry, never a second runtime data source. */
    public function installMissingProfiles(): void
    {
        $file = dirname(__DIR__, 3) . '/resources/model-specifications-bootstrap.json';
        $revisionsFile = dirname(__DIR__, 3) . '/resources/model-specifications-revisions.json';
        $version = hash('sha256', hash_file('sha256', $file) . hash_file('sha256', $revisionsFile));
        if (get_option(self::VERSION_OPTION) === $version) return;
        $bootstrap = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) return; // Keep malformed existing data intact; resolver reports missing profiles safely.
        // Add missing models; apply reviewed corrections only to the exact known old data.
        // Unknown/manual edits and explicit empty entries remain untouched.
        $revisions = json_decode((string) file_get_contents($revisionsFile), true, 512, JSON_THROW_ON_ERROR);
        $merged = $stored + $bootstrap;
        foreach ($revisions as $key => $acceptedHashes) {
            $entry = $stored[$key] ?? null;
            if (!is_array($entry) || !isset($bootstrap[$key])) continue;
            $fingerprint = hash('sha256', json_encode([$entry['rows'] ?? [], $entry['source_url'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (in_array($fingerprint, $acceptedHashes, true)) {
                $merged[$key] = array_replace($entry, $bootstrap[$key]);
            }
        }
        if ($merged !== $stored) update_option(self::OPTION, $merged, false);
        update_option(self::VERSION_OPTION, $version, false);
    }

    public function resolve(string $modelKey): array
    {
        $key = ModelIdentity::canonical($modelKey);
        $all = get_option(self::OPTION, []);
        $entry = is_array($all) && isset($all[$key]) && is_array($all[$key]) ? $all[$key] : [];
        $rows = [];
        if (!isset($entry['model_key']) || ModelIdentity::canonical((string)$entry['model_key']) === $key) {
            foreach (is_array($entry['rows'] ?? null) ? $entry['rows'] : [] as $row) {
                if (!is_array($row) || !is_scalar($row['label'] ?? null) || !is_scalar($row['value'] ?? null)) continue;
                $label=trim((string)$row['label']); $value=trim((string)$row['value']);
                if ($label!=='' && $value!=='') $rows[]=['label'=>$label,'value'=>$value];
            }
        }
        $url=is_scalar($entry['source_url'] ?? null) ? trim((string)$entry['source_url']) : '';
        $status=$rows===[] ? 'missing_profile' : ($this->hasModelSource($url) ? 'ready' : 'missing_model_source');
        $warnings=match($status) {
            'missing_profile'=>['A kiválasztott modellhez még nincs ellenőrzött műszaki adatlap ('.$key.'). A készülék adatai menthetők; más modell adatait nem kapcsoljuk hozzá.'],
            'missing_model_source'=>['A műszaki adatlap elérhető, de a modellspecifikus Apple-forráslink még ellenőrzendő ('.$key.').'],
            default=>[],
        };
        return ['model_key'=>$key,'rows'=>$rows,'source_url'=>$status==='ready' ? $url : '',
            'source_title'=>(string)($entry['source_title']??''),'fetched_at'=>(string)($entry['fetched_at']??''),
            'status'=>$status,'warnings'=>$warnings];
    }

    private function hasModelSource(string $url): bool
    {
        $parts=parse_url($url);
        if (($parts['scheme']??'')!=='https') return false;
        $host=$parts['host']??''; $path=$parts['path']??'';
        if ($host==='support.apple.com') return (bool)preg_match('~/[0-9]{6}/?$~',$path);
        if (!in_array($host,['apple.com','www.apple.com'],true)) return false;
        // Reused family pages can now describe a different generation: report rather than guess.
        return (bool)preg_match('~^/(apple-watch-(se-3|series-11|ultra-3)|ipad-11)/specs/?$~',$path);
    }
}
