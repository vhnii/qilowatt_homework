<?php

declare(strict_types=1);

namespace App;

use App\Api\EleringClient;
use App\Cache\FileCache;
use RuntimeException;

final class PriceLoader
{
    private bool $servedStale = false;

    public function __construct(
        private readonly EleringClient $client,
        private readonly FileCache $cache,
        private readonly int $ttl,
    ) {
    }

    public function rowsFor(LocalDay $day, string $date, string $region): array
    {
        $key = $region . '-' . $date;

        $rows = $this->cache->get($key);

        if ($rows !== null) {
            return $rows;
        }

        try {
            $rows = $this->client->getPrices(
                $day->start()->format('Y-m-d\TH:i:s.v\Z'),
                $day->end()->modify('-1 second')->format('Y-m-d\TH:i:s.v\Z'),
                $region,
            );

            if ($rows !== []) {
                $this->cache->set($key, $rows, $this->ttl);
            }

            return $rows;
        } catch (RuntimeException $e) {
            $rows = $this->cache->getStale($key);

            if ($rows === null) {
                throw $e;
            }

            $this->servedStale = true;

            return $rows;
        }
    }

    public function servedStale(): bool
    {
        return $this->servedStale;
    }
}
