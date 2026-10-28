@extends('layouts.app')
@section('title', 'Your profile')
@section('content')
<section class="page-heading"><div><div class="eyebrow">ACCOUNT SETTINGS</div><h1>Your profile</h1><p>Manage your personal information and sign-in security.</p></div></section>
<section class="profile-grid">
    <form method="POST" action="{{ route('profile.update') }}" class="panel auth-form">@csrf @method('PUT')<h2>Account details</h2>
        <label>Full name<input name="name" value="{{ old('name', $user->name) }}" required></label>
        <label>Email address<input value="{{ $user->email }}" disabled></label>
        <label>Phone number<input name="phone" value="{{ old('phone', $user->phone) }}" type="tel"></label>
        <label>Service desk<input value="{{ $user->serviceDesk?->name ?? 'Not assigned' }}" disabled></label>
        <button class="button button-primary">Save profile</button>
    </form>
    <form method="POST" action="{{ route('profile.password') }}" class="panel auth-form">@csrf @method('PUT')<h2>Change password</h2>
        <label>Current password<input name="current_password" type="password" required autocomplete="current-password"></label>
        <label>New password<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
        <label>Confirm new password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
        <button class="button button-primary">Change password</button>
    </form>
</section>
@endsection
