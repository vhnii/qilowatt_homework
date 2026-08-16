<?php

declare(strict_types=1);

namespace App\Price;

final readonly class PriceConverter
{
    public function __construct(
        private float $vatRate,
        private float $networkFee,
        private float $sellerMargin,
    ) {
    }

    // Price per kWh without VAT (margin + network fee included).
    public function toCentsPerKwh(float $eurPerMwh): float
    {
        return ($eurPerMwh / 10) + $this->networkFee + $this->sellerMargin;
    }

    // Price with VAT
    public function withVat(float $eurPerMwh): float
    {
        return $this->toCentsPerKwh($eurPerMwh) * (1 + $this->vatRate);
    }
}
