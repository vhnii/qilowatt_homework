<?php

declare(strict_types=1);

$formValues ??= ['name' => '', 'email' => '', 'phone' => ''];
$errors ??= [];

$field = static function (string $name) use ($formValues, $errors): string {
    $base = 'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none ';

    return $base . (isset($errors[$name])
        ? 'border-rose-400 focus:border-rose-500'
        : 'border-slate-300 focus:border-slate-900');
};
?>

<form method="post" action="submit.php" class="mt-4 space-y-4">

    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
    <input type="hidden" name="window" value="<?= htmlspecialchars((string) $window) ?>">

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="name" class="text-xs font-medium text-slate-600">Nimi</label>
            <input type="text" id="name" name="name" required maxlength="100"
                   value="<?= htmlspecialchars($formValues['name']) ?>"
                   class="<?= $field('name') ?>">
            <?php if (isset($errors['name'])): ?>
                <p class="mt-1 text-xs text-rose-600"><?= htmlspecialchars($errors['name']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="email" class="text-xs font-medium text-slate-600">E-post</label>
            <input type="email" id="email" name="email" required
                   value="<?= htmlspecialchars($formValues['email']) ?>"
                   class="<?= $field('email') ?>">
            <?php if (isset($errors['email'])): ?>
                <p class="mt-1 text-xs text-rose-600"><?= htmlspecialchars($errors['email']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="phone" class="text-xs font-medium text-slate-600">Telefon</label>
            <input type="tel" id="phone" name="phone" required
                   value="<?= htmlspecialchars($formValues['phone']) ?>"
                   class="<?= $field('phone') ?>">
            <?php if (isset($errors['phone'])): ?>
                <p class="mt-1 text-xs text-rose-600"><?= htmlspecialchars($errors['phone']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Saada
        </button>
        <p class="text-xs text-slate-500">
            Tulemus saadetakse e-kirjaga valitud kuupäeva <?= htmlspecialchars($date) ?> näitajatega.
        </p>
    </div>
</form>
