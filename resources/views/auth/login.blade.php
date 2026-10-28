@extends('layouts.app')
@section('title', 'Staff sign in')
@section('content')
<section class="auth-shell"><div class="auth-intro"><div class="eyebrow">STAFF AND ADMIN</div><h1>Welcome back.</h1><p>Sign in to manage your service desk or administer the queue.</p></div>
<form method="POST" action="{{ route('login.submit') }}" class="panel auth-form">@csrf
    <h2>Sign in</h2><label>Email address<input name="email" type="email" required autocomplete="username" value="{{ old('email') }}"></label>
    <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
    <label class="check-label"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
    <button class="button button-primary button-wide">Sign in</button>
    <a class="text-link" href="{{ route('password.request') }}">Forgot your password?</a>
</form></section>
@endsection
