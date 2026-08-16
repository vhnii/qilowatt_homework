<?php

declare(strict_types=1);


require __DIR__ . '/../vendor/autoload.php';

use App\Api\EleringClient;
use App\Api\HttpClient;
use App\LocalDay;
use App\Price\Price;
use App\Price\PriceConverter;

$config   = require __DIR__ . '/../config/config.php';
$timezone = new DateTimeZone($config['timezone']);

$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$date  = is_string($_GET['date'] ?? null) ? $_GET['date'] : $today;


$window = filter_var(
    $_GET['window'] ?? $config['default_window_hours'],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 6]],
);

if ($window === false) {
    $window = $config['default_window_hours'];
}

$priceConverter = new PriceConverter(
    $config['vat_rate'],
    $config['network_fee'],
    $config['seller_margin'],
);

$periods = [];
$prices = null;
$error = null;

try {

    $day = new LocalDay($date, $timezone);

    $client = new EleringClient(
        new HttpClient($config['api_base_url'], $config['http_timeout']),
    );

    $periods = $client->getPrices(
        $day->start()->format('Y-m-d\TH:i:s.v\Z'),
        $day->end()->modify('-1 second')->format('Y-m-d\TH:i:s.v\Z'),
        $config['region'],
    );


    if ($periods !== [] && count($periods) * $periods[0]->durationInSeconds >= $window * 3600) {
        $prices = new Price($periods);
    }
} catch (InvalidArgumentException $e) {
    $error = 'Vigane kuupäev.';
} catch (Throwable $e) {
    $error = 'Hindade laadimine ebaõnnestus. Elering ei vastanud.';
}



$cheapestWindow = $prices?->cheapestWindow($window);
$mostExpensiveWindow = $prices?->mostExpensiveWindow($window);
$average = $prices?->average();


$startsIn = static function (?array $window): array {
    $starts = [];

    foreach ($window ?? [] as $period) {
        $starts[$period->start->getTimestamp()] = true;
    }

    return $starts;
};

$cheapestStarts = $startsIn($cheapestWindow);
$mostExpensiveStarts  = $startsIn($mostExpensiveWindow);


// Chart data
$labels = [];
$values = [];
$colors = [];


$cheapestPeriodBand = [null, null];

foreach ($periods as $i => $period) {
    $labels[] = $period->start->setTimezone($timezone)->format('H:i');
    $values[] = round($priceConverter->withVat($period->eurPerMwh), 2);

    $colors[] = $period->eurPerMwh < $average ? '#34d399' : '#fb7185';

    if (isset($cheapestStarts[$period->start->getTimestamp()])) {
        $cheapestPeriodBand[0] ??= $i;
        $cheapestPeriodBand[1] = $i;
    }

}

$fmt = static fn (float $n): string => number_format($n, 2, ',', ' ');
?>
<!doctype html>
<html lang="et">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Elektri Börsihinnad</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">

<div class="mx-auto max-w-5xl px-4 py-8 sm:py-12">

    <header class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            Elektri börsihind
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Nord Pool päev-ette hinnad, piirkond <?= htmlspecialchars(strtoupper($config['region'])) ?>.
        </p>
    </header>

    <form method="get" class="mb-8 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-1">
            <label for="date" class="text-xs font-medium text-slate-600">Kuupäev</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none">
        </div>

        <div class="flex flex-col gap-1">
            <label for="window" class="text-xs font-medium text-slate-600">Akna pikkus</label>
            <select id="window" name="window"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none">
                <?php foreach (range(1, 6) as $h): ?>
                    <option value="<?= $h ?>" <?= $h === $window ? 'selected' : '' ?>><?= $h ?> tundi</option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Näita
        </button>
    </form>

    <?php if ($error !== null): ?>

        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php elseif ($prices === null): ?>

        <div class="rounded-xl border border-slate-200 bg-white p-6 text-center">
            <p class="font-medium">Selle päeva hindu pole veel avaldatud.</p>
            <p class="mt-1 text-sm text-slate-500">
                Järgmise päeva hinnad ilmuvad peale kella 14:00.
            </p>
        </div>

    <?php else: ?>

        <?php
        $cheapAvg = $priceConverter->withVat((new Price($cheapestWindow))->average());
        $mostExpensiveAvg = $priceConverter->withVat((new Price($mostExpensiveWindow))->average());
        $stats = [
            ['Keskmine', $fmt($priceConverter->withVat($average)), null, 'bg-white'],
            ['Miinimum', $fmt($priceConverter->withVat($prices->cheapest()->eurPerMwh)),
                $prices->cheapest()->start->setTimezone($timezone)->format('H:i'), 'bg-white'],
            ['Maksimum', $fmt($priceConverter->withVat($prices->mostExpensive()->eurPerMwh)),
                $prices->mostExpensive()->start->setTimezone($timezone)->format('H:i'), 'bg-white'],
            ["Odavaim {$window} h", $fmt($cheapAvg),
                'alates ' . $cheapestWindow[0]->start->setTimezone($timezone)->format('H:i'), 'bg-emerald-50'],
            ["Kalleim {$window} h", $fmt($mostExpensiveAvg),
                'alates ' . $mostExpensiveWindow[0]->start->setTimezone($timezone)->format('H:i'), 'bg-rose-50'],
        ];
        ?>

        <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <?php foreach ($stats as [$label, $value, $note, $bg]): ?>
                <div class="rounded-xl border border-slate-200 <?= $bg ?> p-4 shadow-sm">
                    <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($label) ?></p>
                    <p class="mt-1 text-xl font-semibold tabular-nums"><?= $value ?></p>
                    <p class="text-xs text-slate-400">
                        s/kWh<?= $note !== null ? ' &middot; ' . htmlspecialchars($note) : '' ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mb-8 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="h-64 sm:h-80">
                <canvas id="chart"></canvas>
            </div>
            <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span><span class="mr-1 inline-block h-2 w-2 rounded-sm bg-emerald-400"></span>alla keskmise</span>
                <span><span class="mr-1 inline-block h-2 w-2 rounded-sm bg-rose-400"></span>üle keskmise</span>
                <span><span class="mr-1 inline-block h-3 w-4 rounded-sm bg-emerald-100"></span>odavaim <?= $window ?> h</span>
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="max-h-[32rem] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Algus</th>
                            <th class="px-4 py-2 text-right font-medium">s/kWh<br>km-ta</th>
                            <th class="px-4 py-2 text-right font-medium">s/kWh<br>km-ga</th>
                            <th class="px-4 py-2 text-left font-medium">Aken</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($periods as $period): ?>
                            <?php
                            $inCheap = isset($cheapestStarts[$period->start->getTimestamp()]);
                            $inExpensive  = isset($mostExpensiveStarts[$period->start->getTimestamp()]);
                            $rowBg   = $inCheap ? 'bg-emerald-50' : ($inExpensive ? 'bg-rose-50' : '');
                            ?>
                            <tr class="<?= $rowBg ?>">
                                <td class="px-4 py-2 tabular-nums">
                                    <?= $period->start->setTimezone($timezone)->format('H:i') ?>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-slate-500">
                                    <?= $fmt($priceConverter->toCentsPerKwh($period->eurPerMwh)) ?>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium <?= $period->eurPerMwh < $average ? 'text-emerald-700' : 'text-rose-700' ?>">
                                    <?= $fmt($priceConverter->withVat($period->eurPerMwh)) ?>
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-500">
                                    <?php if ($inCheap): ?>odavaim<?php elseif ($inExpensive): ?>kalleim<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>

            const average = <?= json_encode(round($priceConverter->withVat($average), 2)) ?>;
            const labels  = <?= json_encode($labels) ?>;


            const cheapestBand = {
                id: 'cheapestBand',
                beforeDatasetsDraw(chart) {
                    const [from, to] = <?= json_encode($cheapestPeriodBand) ?>;

                    if (from === null) return;

                    const { ctx, chartArea, scales } = chart;

                    const half  = (scales.x.width / labels.length) / 2;
                    const left  = scales.x.getPixelForValue(from) - half;
                    const right = scales.x.getPixelForValue(to) + half;

                    ctx.save();
                    ctx.fillStyle = 'rgba(16, 185, 129, 0.15)';
                    ctx.fillRect(left, chartArea.top, right - left, chartArea.bottom - chartArea.top);
                    ctx.restore();
                },
            };

            new Chart(document.getElementById('chart'), {
                plugins: [cheapestBand],
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Hind',
                            data: <?= json_encode($values) ?>,
                            backgroundColor: <?= json_encode($colors) ?>,
                            borderWidth: 0,
                        },
                        {
                            type: 'line',
                            label: 'Päeva keskmine',
                            backgroundColor: '#64748b',
                            data: labels.map(() => average),
                            borderColor: '#64748b',
                            borderDash: [4, 4],
                            borderWidth: 1.5,
                            pointRadius: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: (event, elements) => {
                        event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                    },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: c => c.dataset.label + ': ' + c.formattedValue + ' s/kWh',
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                autoSkip: true,
                                maxTicksLimit: 12,
                                maxRotation: 0,
                            },
                        },
                        y: {
                            grid: { color: '#f1f5f9' },
                            ticks: { callback: v => v + ' s' },
                        },
                    },
                },
            });
        </script>

    <?php endif; ?>

    <footer class="mt-10 text-center text-xs text-slate-400">
        Andmed: <a class="text-slate-600" href="https://dashboard.elering.ee/assets/swagger-ui/index.html">Elering avalik API</a>. Hinnad sisaldavad võrgutasu, müüja marginaali ja käibemaksu.
    </footer>

</div>

</body>
</html>
