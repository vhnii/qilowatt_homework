<?php

declare(strict_types=1);

namespace App\Api;

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
        return $data['data'][$region] ?? [];
    }
}
