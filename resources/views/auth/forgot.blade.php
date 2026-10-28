@extends('layouts.app')
@section('title', 'Reset password')
@section('content')
<section class="auth-shell"><div class="auth-intro"><div class="eyebrow">ACCOUNT RECOVERY</div><h1>Reset your password.</h1><p>Enter your registered phone number. If it matches an active account, a one-time code will be sent by SMS.</p></div>
<form method="POST" action="{{ route('password.otp') }}" class="panel auth-form">@csrf<h2>Request a code</h2>
    <label>Phone number<input name="phone" type="tel" required autocomplete="tel" value="{{ old('phone') }}" placeholder="+233..."></label>
    <button class="button button-primary button-wide">Send reset code</button>
    <a class="text-link" href="{{ route('password.reset') }}">I already have a code</a>
</form></section>
@endsection
