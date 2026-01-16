<?php

namespace App\Email;

interface EmailSender
{
    public function send(string $to, string $subject, string $body): bool;
}
