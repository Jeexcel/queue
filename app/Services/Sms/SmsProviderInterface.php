<?php

namespace App\Services\Sms;

interface SmsProviderInterface
{
    public function send(string $phone, string $message): void;
}
