@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<section class="auth-shell"><div class="auth-intro"><div class="eyebrow">ACCOUNT RECOVERY</div><h1>Choose a new password.</h1><p>Your reset code expires after five minutes and can only be used once.</p></div>
<form method="POST" action="{{ route('password.update') }}" class="panel auth-form">@csrf<h2>Verify your code</h2>
    <label>Registered phone number<input name="phone" type="tel" required autocomplete="tel"></label>
    <label>Six-digit code<input name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
    <label>New password<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
    <label>Confirm new password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
    <button class="button button-primary button-wide">Reset password</button>
</form></section>
@endsection
