<?php

namespace App\Services\Sms;

use App\Models\SmsSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NaloSmsProvider implements SmsProviderInterface
{
    public function __construct(private readonly SmsSetting $setting)
    {
    }

    public function send(string $phone, string $message): void
    {
        $credentials = $this->setting->credentials ?? [];
        if (blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
            throw new RuntimeException('Nalo credentials are not configured.');
        }

        $response = Http::timeout(15)
            ->asJson()
            ->post($this->setting->endpoint ?: 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-message/', [
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'msisdn' => $phone,
                'message' => $message,
                'sender_id' => $this->setting->sender_id,
            ])
            ->throw();

        if ((string) $response->json('status') !== '1701') {
            throw new RuntimeException('Nalo did not accept the SMS request.');
        }
    }
}
