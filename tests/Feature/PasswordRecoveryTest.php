<?php

namespace Tests\Feature;

use App\Models\PasswordResetOtp;
use App\Models\SmsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_otp_is_hidden_from_the_response_and_can_only_reset_the_password_once(): void
    {
        Http::fake(fn () => Http::response(['status' => 'ok'], 200));
        SmsSetting::query()->firstOrFail()->update([
            'provider' => 'hubtel',
            'sender_id' => 'QueueDesk',
            'credentials' => ['username' => 'client', 'password' => 'secret'],
        ]);
        $user = User::factory()->create(['phone' => '+233200000001']);

        $response = $this->post('/forgot-password', ['phone' => $user->phone]);
        $response->assertRedirect()->assertSessionHas('status', 'If an active account matches this number, a password reset code will be sent.');
        $request = Http::recorded()->first()[0];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        preg_match('/\b(\d{6})\b/', $query['Content'], $match);
        $this->assertNotEmpty($match[1] ?? null);
        $otp = $match[1];
        $this->assertStringNotContainsString($otp, $response->getContent());
        $this->assertNotSame($otp, PasswordResetOtp::query()->firstOrFail()->otp_hash);

        $this->post('/reset-password', [
            'phone' => $user->phone,
            'otp' => $otp,
            'password' => 'a-strong-new-password',
            'password_confirmation' => 'a-strong-new-password',
        ])->assertRedirect('/login');
        $this->assertTrue(Hash::check('a-strong-new-password', $user->fresh()->password));
        $this->assertNotNull(PasswordResetOtp::query()->firstOrFail()->consumed_at);

        $this->from('/reset-password')->post('/reset-password', [
            'phone' => $user->phone,
            'otp' => $otp,
            'password' => 'another-strong-password',
            'password_confirmation' => 'another-strong-password',
        ])->assertSessionHasErrors('otp');
    }

    public function test_unknown_phone_receives_the_same_recovery_response(): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['phone' => '+233200000002'])
            ->assertSessionHas('status', 'If an active account matches this number, a password reset code will be sent.');
        $this->assertSame(0, PasswordResetOtp::query()->count());
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->create(['phone' => '+233200000003']);
        PasswordResetOtp::query()->create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->subSecond(),
            'last_sent_at' => now()->subMinutes(6),
        ]);

        $this->from('/reset-password')->post('/reset-password', [
            'phone' => $user->phone,
            'otp' => '123456',
            'password' => 'a-strong-new-password',
            'password_confirmation' => 'a-strong-new-password',
        ])->assertSessionHasErrors('otp');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_nalo_selection_uses_the_configured_nalo_endpoint(): void
    {
        Http::fake(fn () => Http::response(['status' => '1701', 'job_id' => 'test'], 200));
        SmsSetting::query()->firstOrFail()->update([
            'provider' => 'nalo',
            'endpoint' => 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-message/',
            'sender_id' => 'QueueDesk',
            'credentials' => ['username' => 'nalo-user', 'password' => 'nalo-secret'],
        ]);

        app(\App\Services\Sms\SmsProviderManager::class)->send('+233200000004', 'Test message');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), 'sms.nalosolutions.com')
            && ($request->data()['username'] ?? null) === 'nalo-user'
            && ($request->data()['msisdn'] ?? null) === '+233200000004'
            && ($request->data()['message'] ?? null) === 'Test message');
    }
}
