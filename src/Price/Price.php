<?php

declare(strict_types=1);

namespace App\Price;

use InvalidArgumentException;

final class Price
{
    private readonly array $periods;

    public function __construct(array $periods)
    {
        if ($periods === []) {
            throw new InvalidArgumentException('Päevas peab olema vähemalt üks hinnaperiood.');
        }

        $this->periods = $periods;
    }


    public function cheapest(): PricePeriod
    {
        $cheapest = $this->periods[0];

        foreach ($this->periods as $period) {
            if ($period->eurPerMwh < $cheapest->eurPerMwh) {
                $cheapest = $period;
            }
        }

        return $cheapest;
    }


    public function mostExpensive(): PricePeriod
    {
        $mostExpensive = $this->periods[0];

        foreach ($this->periods as $period) {
            if ($period->eurPerMwh > $mostExpensive->eurPerMwh) {
                $mostExpensive = $period;
            }
        }

        return $mostExpensive;
    }

    public function average(): float
    {
        return $this->averageOf($this->periods);
    }


    public function cheapestWindow(int $hours): array
    {
        $length = $this->periodsPerWindow($hours);

        $cheapest = array_slice($this->periods, 0, $length);
        $cheapestAverage = $this->averageOf($cheapest);

        for ($i = 1; $i + $length <= count($this->periods); $i++) {
            $window = array_slice($this->periods, $i, $length);
            $average = $this->averageOf($window);

            if ($average < $cheapestAverage) {
                $cheapest = $window;
                $cheapestAverage = $average;
            }
        }

        return $cheapest;
    }


    public function mostExpensiveWindow(int $hours): array
    {
        $length = $this->periodsPerWindow($hours);

        $mostExpensive = array_slice($this->periods, 0, $length);
        $mostExpensiveAverage = $this->averageOf($mostExpensive);

        for ($i = 1; $i + $length <= count($this->periods); $i++) {
            $window = array_slice($this->periods, $i, $length);
            $average = $this->averageOf($window);

            if ($average > $mostExpensiveAverage) {
                $mostExpensive = $window;
                $mostExpensiveAverage = $average;
            }
        }

        return $mostExpensive;
    }


    private function periodsPerWindow(int $hours): int
    {

        $length = intdiv($hours * 3600, $this->periods[0]->durationInSeconds);


        if ($length > count($this->periods)) {
            throw new InvalidArgumentException("Päev on lühem kui valitud ajaaken, {$hours} tundi.");
        }

        return $length;
    }


    private function averageOf(array $periods): float
    {
        $weightedSum = 0.0;
        $totalSeconds = 0;

        foreach ($periods as $period) {
            $weightedSum += $period->eurPerMwh * $period->durationInSeconds;
            $totalSeconds += $period->durationInSeconds;
        }

        return $weightedSum / $totalSeconds;
    }
}
