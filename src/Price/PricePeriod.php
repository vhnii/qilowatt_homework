<?php

declare(strict_types=1);

namespace App\Price;

use DateTimeImmutable;

final readonly class PricePeriod
{
    public function __construct(
        public DateTimeImmutable $start,
        public int $durationInSeconds,
        public float $eurPerMwh,
    ) {
    }

    // The API gives a start timestamp but no duration, so it is inferred from the gap
    // to the neighbouring period. That is what keeps 15- and 60-minute data both working.
    public static function listFromRows(array $rows): array
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

            $periods[] = new self(
                new DateTimeImmutable('@' . $row['timestamp']),
                $duration,
                (float) $row['price'],
            );
        }

        return $periods;
    }
}
