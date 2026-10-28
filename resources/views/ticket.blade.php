@extends('layouts.app')

@section('title', 'Your ticket '.$ticket->ticket_number)
@section('content')
<section class="ticket-page">
    <div class="ticket-actions no-print"><a href="{{ route('home') }}" class="text-link">← Back to home</a><button type="button" class="button button-dark" onclick="window.print()">Print ticket</button></div>
    <article class="print-ticket">
        <div class="ticket-brand">{{ $organization }}</div>
        <div class="ticket-divider"></div>
        <div class="ticket-label">YOUR QUEUE NUMBER</div>
        <div class="ticket-number">{{ $ticket->ticket_number }}</div>
        <div class="ticket-issued">Issued {{ $ticket->issued_at->format('M j, Y · g:i A') }}</div>
        <div class="ticket-divider"></div>
        <p>Please wait for your number to appear on the waiting-room display.</p>
        <small>Session: {{ $ticket->queueSession->label }}</small>
    </article>
</section>
@push('scripts')
<script>window.addEventListener('load', () => window.setTimeout(() => window.print(), 350));</script>
@endpush
@endsection
