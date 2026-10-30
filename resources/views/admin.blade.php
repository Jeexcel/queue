@php
    $adminPages = [
        'overview' => ['title' => 'Queue overview', 'description' => 'Manage today’s queue and see a snapshot of operations.'],
        'sessions' => ['title' => 'Queue sessions', 'description' => 'Start, end, and review queue sessions.'],
        'monitor' => ['title' => 'Queue monitor', 'description' => 'Review active tickets and recover interrupted assignments.'],
        'desks' => ['title' => 'Service desks', 'description' => 'Configure desks and review their current availability.'],
        'staff' => ['title' => 'Staff accounts', 'description' => 'Create staff accounts and manage desk assignments.'],
        'history' => ['title' => 'Ticket history', 'description' => 'Review ticket activity and recent queue sessions.'],
        'settings' => ['title' => 'Queue settings', 'description' => 'Configure organization details and ticket behavior.'],
        'sms' => ['title' => 'SMS settings', 'description' => 'Manage the recovery provider and test message delivery.'],
        'audit' => ['title' => 'Audit log', 'description' => 'Review recent administrative actions.'],
    ];
    $currentPage = $adminPages[$section];
@endphp
@extends('layouts.app')
@section('title', $currentPage['title'].' · Administration')
@section('main-class', 'admin-main')
@section('content')
<div class="admin-layout">
    <aside class="admin-sidebar" aria-label="Administration">
        <div class="admin-sidebar-heading">
            <span class="brand-mark">Q</span>
            <div><strong>Administration</strong><small>QUEUE CONTROL</small></div>
        </div>
        <nav class="admin-sidebar-nav" aria-label="Admin pages">
            <a class="admin-nav-link {{ $section === 'overview' ? 'active' : '' }}" href="{{ route('admin.index') }}" @if($section === 'overview') aria-current="page" @endif>Overview</a>
            <a class="admin-nav-link {{ $section === 'sessions' ? 'active' : '' }}" href="{{ route('admin.sessions') }}" @if($section === 'sessions') aria-current="page" @endif>Queue sessions</a>
            <a class="admin-nav-link {{ $section === 'monitor' ? 'active' : '' }}" href="{{ route('admin.monitor') }}" @if($section === 'monitor') aria-current="page" @endif>Queue monitor</a>
            <a class="admin-nav-link {{ $section === 'desks' ? 'active' : '' }}" href="{{ route('admin.desks.index') }}" @if($section === 'desks') aria-current="page" @endif>Service desks</a>
            <a class="admin-nav-link {{ $section === 'staff' ? 'active' : '' }}" href="{{ route('admin.staff.index') }}" @if($section === 'staff') aria-current="page" @endif>Staff accounts</a>
            <a class="admin-nav-link {{ $section === 'history' ? 'active' : '' }}" href="{{ route('admin.history') }}" @if($section === 'history') aria-current="page" @endif>Ticket history</a>
            <a class="admin-nav-link {{ $section === 'settings' ? 'active' : '' }}" href="{{ route('admin.settings.page') }}" @if($section === 'settings') aria-current="page" @endif>Queue settings</a>
            <a class="admin-nav-link {{ $section === 'sms' ? 'active' : '' }}" href="{{ route('admin.sms.index') }}" @if($section === 'sms') aria-current="page" @endif>SMS settings</a>
            <a class="admin-nav-link {{ $section === 'audit' ? 'active' : '' }}" href="{{ route('admin.audit') }}" @if($section === 'audit') aria-current="page" @endif>Audit log</a>
        </nav>
        <a class="admin-sidebar-footer" href="{{ route('display') }}" target="_blank" rel="noopener">Open waiting-room display <span aria-hidden="true">↗</span></a>
    </aside>
    <div class="admin-content">
        <section class="page-heading admin-page-heading">
            <div><div class="eyebrow">ADMINISTRATION</div><h1>{{ $currentPage['title'] }}</h1><p>{{ $currentPage['description'] }}</p></div>
            @if($section === 'overview')
                <div class="availability-pill {{ $session ? '' : 'pill-muted' }}"><span class="live-dot"></span>{{ $session ? 'Queue session open' : 'No open session' }}</div>
            @endif
        </section>
        @include('admin.pages.'.$section)
    </div>
</div>
@endsection
