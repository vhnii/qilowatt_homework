<?php

declare(strict_types=1);

namespace App\Submission;

use App\Price\Price;
use App\Price\PriceConverter;
use DateTimeImmutable;
use DateTimeZone;


final class SubmissionMail
{
    public function __construct(
        private readonly SubmissionForm $form,
        private readonly Price $prices,
        private readonly PriceConverter $converter,
        private readonly int $windowHours,
        private readonly string $date,
        private readonly string $region,
        private readonly string $repoUrl,
        private readonly ?string $commitSha,
        private readonly DateTimeZone $timezone,
    ) {
    }

    public function subject(): string
    {
        return sprintf(
            'Kodutöö - %s (%s, %s)',
            $this->form->name,
            $this->date,
            strtoupper($this->region),
        );
    }

    public function body(): string
    {
        $cheapest = $this->prices->cheapest();
        $mostExpensive = $this->prices->mostExpensive();

        $window = $this->prices->cheapestWindow($this->windowHours);
        $windowAverage = (new Price($window))->average();

        $lines = [
            'KANDIDAAT',
            '  Nimi:            ' . $this->form->name,
            '  E-post:          ' . $this->form->email,
            '  Telefon:         ' . $this->form->phone,
            '',
            'REPO',
            '  URL:             ' . $this->repoUrl,
            '  Viimane commit:  ' . ($this->commitSha ?? 'teadmata'),
            '',
            'PÄRING',
            '  Kuupäev:         ' . $this->date,
            '  Hinnapiirkond:   ' . strtoupper($this->region),
            '  Perioodi pikkus: ' . intdiv($cheapest->durationInSeconds, 60) . ' min',
            '',
            'NÄITAJAD (senti/kWh, sisaldab võrgutasu, müüja marginaali ja käibemaksu)',
            '  Keskmine:        ' . $this->price($this->prices->average()),
            '  Miinimum:        ' . $this->price($cheapest->eurPerMwh) . ' kell ' . $this->time($cheapest->start),
            '  Maksimum:        ' . $this->price($mostExpensive->eurPerMwh) . ' kell ' . $this->time($mostExpensive->start),
            '',
            sprintf('  Odavaim %dh aken algab kell %s', $this->windowHours, $this->time($window[0]->start)),
            '  Selle akna keskmine: ' . $this->price($windowAverage),
            '',
            'SAATMINE',
            '  Aeg:             ' . (new DateTimeImmutable('now', $this->timezone))->format('d.m.Y H:i:s') . ' (Europe/Tallinn)',
            '  PHP versioon:    ' . PHP_VERSION,
        ];

        return implode("\n", $lines) . "\n";
    }


    private function price(float $eurPerMwh): string
    {
        return $this->number($this->converter->withVat($eurPerMwh)) . ' s/kWh';
    }

    private function number(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }

    private function time(DateTimeImmutable $at): string
    {
        return $at->setTimezone($this->timezone)->format('H:i');
    }
}
