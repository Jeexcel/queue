<?php

namespace App\Services\Sms;

use App\Models\SmsSetting;

class SmsProviderManager
{
    public function send(string $phone, string $message): void
    {
        $setting = SmsSetting::query()->firstOrFail();
        $provider = match ($setting->provider) {
            'hubtel' => new HubtelSmsProvider($setting),
            'nalo' => new NaloSmsProvider($setting),
            default => throw new \RuntimeException('The configured SMS provider is not supported.'),
        };
        $provider->send($phone, $message);
    }
}
