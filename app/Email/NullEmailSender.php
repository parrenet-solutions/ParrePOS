<?php

namespace App\Email;

class NullEmailSender implements EmailSender
{
    public function send(string $to, string $subject, string $body): bool
    {
        // No integra SMTP todavía; se marca como enviado en worker.
        return true;
    }
}
