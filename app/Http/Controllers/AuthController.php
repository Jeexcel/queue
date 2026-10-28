<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Services\Sms\SmsProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Please wait before trying again.']);
        }

        $user = User::query()->where('email', $data['email'])->where('is_active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The provided credentials are invalid.']);
        }
        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended($user->role === 'admin' ? route('admin.index') : route('staff.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    public function forgotForm(): View
    {
        return view('auth.forgot');
    }

    public function sendOtp(Request $request, SmsProviderManager $sms): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $rateKey = 'otp:'.$request->ip().':'.hash('sha256', $data['phone']);
        $message = 'If an active account matches this number, a password reset code will be sent.';
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return back()->with('status', $message);
        }
        RateLimiter::hit($rateKey, 3600);

        $user = User::query()->where('phone', $data['phone'])->where('is_active', true)->first();
        if ($user) {
            $otp = (string) random_int(100000, 999999);
            $reset = DB::transaction(function () use ($user, $data, $otp) {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $previous = PasswordResetOtp::query()->where('user_id', $user->id)->latest('id')->lockForUpdate()->first();
                if ($previous && $previous->last_sent_at->gt(now()->subSeconds(60))) {
                    return null;
                }
                PasswordResetOtp::query()->where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

                return PasswordResetOtp::query()->create([
                    'user_id' => $user->id,
                    'phone' => $data['phone'],
                    'otp_hash' => Hash::make($otp),
                    'expires_at' => now()->addMinutes(5),
                    'last_sent_at' => now(),
                ]);
            }, 3);
            if ($reset) {
                try {
                    $sms->send($data['phone'], "Your password reset code is {$otp}. It expires in 5 minutes.");
                } catch (ConnectionException|RequestException|\RuntimeException $exception) {
                    $reset->delete();
                    Log::warning('Password reset SMS delivery failed.', ['provider_error' => class_basename($exception)]);
                }
            }
        }

        return back()->with('status', $message);
    }

    public function resetForm(): View
    {
        return view('auth.reset');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $rateKey = 'otp-verify:'.$request->ip().':'.hash('sha256', $data['phone']);
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            throw ValidationException::withMessages(['otp' => 'Too many attempts. Request a new code and try again later.']);
        }
        RateLimiter::hit($rateKey, 3600);

        $updated = DB::transaction(function () use ($data) {
            $reset = PasswordResetOtp::query()
                ->where('phone', $data['phone'])
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if (! $reset || $reset->expires_at->isPast() || $reset->attempts >= 5) {
                return false;
            }
            if (! Hash::check($data['otp'], $reset->otp_hash)) {
                $reset->increment('attempts');
                return false;
            }
            $user = User::query()->whereKey($reset->user_id)->where('is_active', true)->lockForUpdate()->first();
            if (! $user) {
                return false;
            }
            $user->update(['password' => $data['password']]);
            $reset->update(['consumed_at' => now()]);
            return true;
        }, 3);

        if (! $updated) {
            throw ValidationException::withMessages(['otp' => 'The code is invalid or expired. Request a new code.']);
        }
        RateLimiter::clear($rateKey);
        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }

    public function profile(Request $request): View
    {
        return view('profile', ['user' => $request->user()->load('serviceDesk')]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', \Illuminate\Validation\Rule::unique('users', 'phone')->ignore($request->user()->id)],
        ]);
        $request->user()->update($data);
        return redirect()->route('profile')->with('status', 'Profile updated.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $request->user()->update(['password' => $data['password']]);
        return redirect()->route('profile')->with('status', 'Password changed.');
    }
}
