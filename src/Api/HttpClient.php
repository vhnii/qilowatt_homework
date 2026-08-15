<?php

declare(strict_types=1);

namespace App\Api;

final class HttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function get(string $path, array $query = []): string
    {
        $url = $this->baseUrl . $path;

        if ($query !== []) {
            $url = $url . '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeoutSeconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        // If curl failed throw error
        if ($response === false) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Unexpected HTTP status {$status}");
        }

        return $response;
    }
}
