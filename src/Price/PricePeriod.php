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
}
