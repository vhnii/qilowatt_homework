<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

return [
    'api_base_url' => 'https://dashboard.elering.ee',
    'region'       => 'ee',
    'http_timeout' => 5,

    'cache_dir' => __DIR__ . '/../cache',
    'cache_ttl' => 3600,    // Seconds.

    'timezone' => 'Europe/Tallinn',    // Timezone to show in UI
    'vat_rate'      => 0.24,    // 24% VAT
    'network_fee'   => 0,   // s/kWh
    'seller_margin' => 0,   // s/kWh
    'default_window_hours' => 3,    // Default length of cheapest/most expensive window in hours (1-6)

];
