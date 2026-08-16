<?php

declare(strict_types=1);

namespace App\Api;

use App\Price\PricePeriod;
use DateTimeImmutable;

final class EleringClient
{
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    public function getPrices(string $start, string $end, string $region = 'ee'): array
    {
        $response = $this->http->get('/api/nps/price', [
            'start' => $start,
            'end' => $end,
        ]);

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Elering did not return valid JSON');
        }

        // The API can answer with HTTP 200 and still say the query failed.
        if (empty($data['success'])) {
            throw new \RuntimeException('Elering said the request was not successful');
        }

        // Tomorrow's prices are only published around 14:00, so an empty answer is normal.
        $rows = $data['data'][$region] ?? [];

        return $this->buildPeriods($rows);
    }


    private function buildPeriods(array $rows): array
    {

        usort($rows, fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

        $periods = [];

        foreach ($rows as $i => $row) {

            $next = $rows[$i + 1]['timestamp'] ?? null;
            $previous = $rows[$i - 1]['timestamp'] ?? null;

            if ($next !== null) {
                $duration = $next - $row['timestamp'];
            } elseif ($previous !== null) {
                $duration = $row['timestamp'] - $previous;
            } else {
                $duration = 3600;
            }

            $periods[] = new PricePeriod(
                new DateTimeImmutable('@' . $row['timestamp']),
                $duration,
                (float) $row['price'],
            );
        }

        return $periods;
    }
}
