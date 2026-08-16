<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Api\EleringClient;
use App\Api\HttpClient;
use App\Cache\FileCache;
use App\GitInfo;
use App\LocalDay;
use App\Price\Price;
use App\Price\PriceConverter;
use App\Price\PricePeriod;
use App\PriceLoader;
use App\Submission\Mailer;
use App\Submission\SubmissionForm;
use App\Submission\SubmissionMail;

$config = require __DIR__ . '/../config/config.php';

// POST only. Personal data must never travel in a query string, and this
// endpoint must not be reachable by clicking a link.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

$timezone = new DateTimeZone($config['timezone']);

$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$date  = is_string($_POST['date'] ?? null) ? $_POST['date'] : $today;

$window = filter_var(
    $_POST['window'] ?? $config['default_window_hours'],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 6]],
);

if ($window === false) {
    $window = $config['default_window_hours'];
}

$form   = SubmissionForm::fromPost($_POST);
$errors = [];
$error  = null;

if (!$form->validate()) {
    $errors = $form->errors();
} else {
    try {
        $day = new LocalDay($date, $timezone);

        $loader = new PriceLoader(
            new EleringClient(new HttpClient($config['api_base_url'], $config['http_timeout'])),
            new FileCache($config['cache_dir']),
            $config['cache_ttl'],
        );

        // Recomputed server-side on purpose: anything posted from the browser is
        // user-editable, and the email must report what the app calculated.
        $periods = PricePeriod::listFromRows($loader->rowsFor($day, $date, $config['region']));

        if ($periods === []) {
            throw new RuntimeException('Selle päeva hindu pole veel avaldatud, seega pole midagi saata.');
        }

        $mail = new SubmissionMail(
            $form,
            new Price($periods),
            new PriceConverter($config['vat_rate'], $config['network_fee'], $config['seller_margin']),
            $window,
            $date,
            $config['region'],
            $config['github_repo_url'],
            GitInfo::currentSha(__DIR__ . '/..'),
            $timezone,
        );

        (new Mailer($config['mail']))->send($mail->subject(), $mail->body(), $form->email);

        // POST/Redirect/GET, so refreshing the result page cannot fire a second email.
        header('Location: index.php?date=' . urlencode($date) . '&window=' . $window . '&sent=1');

        exit;
    } catch (InvalidArgumentException $e) {
        $error = 'Vigane kuupäev.';
    } catch (Throwable $e) {
        // Shown, never logged - §2.4 forbids writing personal data to a file,
        // and a silent failure would mean the candidate never learns the
        // assignment was not submitted.
        $error = 'Saatmine ebaõnnestus: ' . $e->getMessage();
    }
}

// Reached only when validation or sending failed, so re-render the form.
$formValues = ['name' => $form->name, 'email' => $form->email, 'phone' => $form->phone];
?>
<!doctype html>
<html lang="et">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saada tulemus</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">

<div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">

    <h1 class="text-2xl font-semibold tracking-tight">Saada tulemus</h1>

    <?php if ($error !== null): ?>
        <div class="mt-6 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php else: ?>
        <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            Palun paranda allpool märgitud väljad.
        </div>
    <?php endif; ?>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <?php require __DIR__ . '/../templates/submit-form.php'; ?>
    </div>

    <p class="mt-6 text-sm">
        <a class="text-slate-600 underline"
           href="index.php?date=<?= urlencode($date) ?>&amp;window=<?= (int) $window ?>">
            Tagasi hindade juurde
        </a>
    </p>
</div>

</body>
</html>
