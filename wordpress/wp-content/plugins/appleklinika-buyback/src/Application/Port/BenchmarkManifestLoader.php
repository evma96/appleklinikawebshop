<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\Port;

use AppleKlinika\Buyback\Domain\Benchmark\BenchmarkManifest;

interface BenchmarkManifestLoader
{
    public function load(string $path): BenchmarkManifest;
}
