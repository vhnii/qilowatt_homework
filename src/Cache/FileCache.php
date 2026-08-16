<?php

declare(strict_types=1);

namespace App\Cache;

final class FileCache
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function get(string $key): ?array
    {
        $entry = $this->read($key);

        if ($entry === null || $entry['expires'] < time()) {
            return null;
        }

        return $entry['value'];
    }

    // An expired entry is still better than nothing when Elering is down.
    public function getStale(string $key): ?array
    {
        return $this->read($key)['value'] ?? null;
    }

    public function set(string $key, array $value, int $ttl): void
    {
        file_put_contents(
            $this->path($key),
            json_encode(['expires' => time() + $ttl, 'value' => $value]),
            LOCK_EX,
        );
    }

    private function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $entry = json_decode((string) file_get_contents($path), true);

        // A truncated or hand-edited file reads as a miss instead of exploding.
        if (!is_array($entry) || !isset($entry['expires'], $entry['value'])) {
            return null;
        }

        return $entry;
    }

    // The key comes from user input, so it is sanitized before it names a file.
    private function path(string $key): string
    {
        return $this->directory . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.json';
    }
}
