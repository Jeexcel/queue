<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Queue Management') · {{ config('app.name', 'Queue') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class')">
    @if(trim($__env->yieldContent('hide-nav')) === '')
        <header class="topbar">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark">Q</span> Queue Desk</a>
            <nav>
                <a href="{{ route('home') }}">Collect ticket</a>
                <a href="{{ route('display') }}">Waiting room</a>
                @auth
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.index') }}">Admin</a>
                    @else
                        <a href="{{ route('staff.index') }}">Staff</a>
                    @endif
                    <a href="{{ route('profile') }}">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button class="nav-button">Sign out</button></form>
                @else
                    <a href="{{ route('login') }}">Staff sign in</a>
                @endauth
            </nav>
        </header>
    @endif
    @if(session('status') || $errors->any())
        <section class="flash-wrap" role="status">
            @if(session('status'))<div class="flash success">{{ session('status') }}</div>@endif
            @foreach($errors->all() as $error)<div class="flash error">{{ $error }}</div>@endforeach
        </section>
    @endif
    <main class="@yield('main-class')">@yield('content')</main>
    @stack('scripts')
</body>
</html>
