<?php

declare(strict_types=1);

namespace App\Submission;

final class SubmissionForm
{
    private array $errors = [];

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
    ) {
    }

    public static function fromPost(array $post): self
    {
        return new self(
            trim((string) ($post['name'] ?? '')),
            trim((string) ($post['email'] ?? '')),
            trim((string) ($post['phone'] ?? '')),
        );
    }


    public function validate(): bool
    {
        $this->errors = [];

        if ($this->name === '') {
            $this->errors['name'] = 'Palun sisesta oma nimi.';
        } elseif (mb_strlen($this->name) > 100) {
            $this->errors['name'] = 'Nimi on liiga pikk.';
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Vigane e-posti aadress.';
        }

        $digits = preg_replace('/\D/', '', $this->phone);

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            $this->errors['phone'] = 'Vigane telefoninumber.';
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
