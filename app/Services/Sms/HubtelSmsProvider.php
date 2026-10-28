<?php

namespace App\Services\Sms;

use App\Models\SmsSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HubtelSmsProvider implements SmsProviderInterface
{
    public function __construct(private readonly SmsSetting $setting)
    {
    }

    public function send(string $phone, string $message): void
    {
        $credentials = $this->setting->credentials ?? [];
        if (blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
            throw new RuntimeException('Hubtel credentials are not configured.');
        }

        Http::timeout(15)
            ->withBasicAuth($credentials['username'], $credentials['password'])
            ->get($this->setting->endpoint ?: 'https://smsc.hubtel.com/v1/messages/send', [
                'From' => $this->setting->sender_id,
                'To' => $phone,
                'Content' => $message,
            ])->throw();
    }
}
