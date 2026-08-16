<?php

declare(strict_types=1);

namespace App\Submission;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;


final class Mailer
{
    public function __construct(private readonly array $config)
    {
    }

    public function send(string $subject, string $body, ?string $replyTo = null): void
    {
        foreach (['host', 'port', 'user', 'pass', 'from', 'to'] as $key) {
            if (empty($this->config[$key])) {
                throw new RuntimeException("Meili seadistus on puudulik: {$key}. Vaata .env faili.");
            }
        }

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->config['host'];
        $mail->Port       = $this->config['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->config['user'];
        $mail->Password   = $this->config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;

        $mail->setFrom($this->config['from'], 'Spotihinnad');
        $mail->addAddress($this->config['to']);


        if ($replyTo !== null) {
            $mail->addReplyTo($replyTo);
        }

        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    }
}
