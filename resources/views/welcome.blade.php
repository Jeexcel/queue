@extends('layouts.app')

@section('title', 'Collect a queue ticket')

@section('content')
<section class="hero-shell">
    <div class="hero-copy">
        @if($settings->logo_url)<img class="organization-logo" src="{{ $settings->logo_url }}" alt="{{ $settings->organization_name }} logo">@endif
        <div class="eyebrow"><span class="live-dot"></span> {{ $active ? 'QUEUE OPEN' : 'QUEUE CLOSED' }}</div>
        <h1>{{ $settings->organization_name }}</h1>
        <p class="lead">Welcome. Collect a number and we’ll call you when it’s your turn.</p>
        @if($active)
            <form method="POST" action="{{ route('tickets.issue') }}" id="ticket-form">
                @csrf
                <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <button class="collect-button" type="submit"><span class="ticket-icon">#</span><span>CLICK TO COLLECT NUMBER<small>One number per visit</small></span><span class="button-arrow">→</span></button>
            </form>
            <p class="subtle">Your ticket will print automatically. Please keep it with you.</p>
        @else
            <div class="closed-notice"><strong>Ticket collection is unavailable</strong><span>There is no open queue session right now. Please ask reception for assistance.</span></div>
        @endif
    </div>
    <aside class="home-aside">
        @if($latestAnnouncement)
            <div class="mini-card">
                <span class="eyebrow">LATEST CALL</span>
                <strong class="mini-ticket">{{ $latestAnnouncement->ticket->ticket_number }}</strong>
                <span>Please proceed to {{ $latestAnnouncement->serviceDesk->name }}</span>
            </div>
        @else
            <div class="mini-card quiet-card"><span class="eyebrow">WAITING ROOM</span><strong>Follow your number</strong><span>Calls appear on the public display.</span><a class="text-link" href="{{ route('display') }}">Open waiting-room display →</a></div>
        @endif
        <div class="help-card"><span class="help-mark">i</span><div><strong>What happens next?</strong><p>Keep your ticket visible and listen for your number on the waiting-room display.</p></div></div>
    </aside>
</section>
@push('scripts')
<script>
    document.getElementById('ticket-form')?.addEventListener('submit', function () {
        const button = this.querySelector('button');
        button.disabled = true;
        button.querySelector('span:nth-child(2)').firstChild.textContent = 'ISSUING YOUR NUMBER…';
    });
</script>
@endpush
@endsection
