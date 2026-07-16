<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\Benchmark;

use AppleKlinika\Buyback\Application\Port\BenchmarkManifestLoader;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkManifest;
use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkSourceSnapshotValidator;
use AppleKlinika\Buyback\Domain\Exception\InvalidValueObjectException;

final class FileBenchmarkManifestLoader implements BenchmarkManifestLoader
{
    public function __construct(private readonly BenchmarkSourceSnapshotValidator $sourceValidator)
    {
    }

    public function load(string $path): BenchmarkManifest
    {
        $manifestPath = realpath($path);
        if ($manifestPath === false || ! is_file($manifestPath) || ! is_readable($manifestPath)) {
            throw new InvalidValueObjectException('Benchmark manifest file is not readable.');
        }
        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            throw new InvalidValueObjectException('Benchmark manifest file could not be read.');
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new InvalidValueObjectException('Benchmark manifest root must be an object.');
        }
        $manifest = BenchmarkManifest::fromArray($data);
        $directory = dirname($manifestPath);

        foreach ($manifest->sourceSnapshots as $source) {
            $sourcePath = realpath($directory . DIRECTORY_SEPARATOR . $source['path']);
            if ($sourcePath === false || ! str_starts_with($sourcePath, $directory . DIRECTORY_SEPARATOR) || ! is_file($sourcePath)) {
                throw new InvalidValueObjectException("Benchmark source snapshot is unavailable: {$source['path']}.");
            }
            if (! hash_equals($source['sha256'], hash_file('sha256', $sourcePath))) {
                throw new InvalidValueObjectException("Benchmark source snapshot checksum mismatch: {$source['path']}.");
            }
            $sourceRaw = file_get_contents($sourcePath);
            $sourceData = $sourceRaw === false ? null : json_decode($sourceRaw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($sourceData)) {
                throw new InvalidValueObjectException("Benchmark source snapshot is not a JSON object: {$source['path']}.");
            }
            $this->sourceValidator->validate($sourceData);
        }

        return $manifest;
    }
}
