<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class LocalDay
{
    private readonly DateTimeImmutable $start;
    private readonly DateTimeImmutable $end;

    public function __construct(string $date, DateTimeZone $timezone)
    {
        $midnight = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if ($midnight === false || $midnight->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Vigane kuupäev, {$date}.");
        }

        $utcTimeZone = new DateTimeZone('UTC');

        $this->start = $midnight->setTimezone($utcTimeZone);

        $this->end = $midnight->modify('+1 day')->setTimezone($utcTimeZone);
    }


    public function start(): DateTimeImmutable
    {
        return $this->start;
    }


    public function end(): DateTimeImmutable
    {
        return $this->end;
    }
}
