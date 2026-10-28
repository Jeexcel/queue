@extends('layouts.app')
@section('title', 'Staff dashboard')
@section('content')
<section class="page-heading">
    <div><div class="eyebrow">STAFF WORKSPACE</div><h1>Good {{ now()->format('H') < 12 ? 'morning' : 'afternoon' }}, {{ $staff->name }}</h1><p>Manage your current customer and stay up to date with the queue.</p></div>
    <div class="availability-pill" id="availability"><span class="live-dot"></span><span>{{ $current ? 'Handling customer' : ($session?->status === 'open' ? 'Available' : 'Session closed') }}</span></div>
</section>
<section class="staff-grid">
    <div class="panel staff-current">
        <div class="panel-heading"><div><span class="eyebrow">YOUR SERVICE DESK</span><h2>{{ $staff->serviceDesk?->name ?? 'No desk assigned' }}</h2></div><span class="status-tag" id="session-status">{{ $session?->status ?? 'closed' }}</span></div>
        <div class="current-customer">
            @if($current)
                <span class="eyebrow">CURRENT TICKET</span>
                <strong id="current-ticket">{{ $current->ticket_number }}</strong>
                <span class="status-tag">{{ ucfirst($current->status) }}</span>
            @else
                <span class="eyebrow">NO ACTIVE TICKET</span>
                <strong class="waiting-dash">—</strong>
                <span class="subtle">Request the next waiting customer when you're ready.</span>
            @endif
        </div>
        <div class="action-row">
            @if($current?->status === 'called')
                <form method="POST" action="{{ route('staff.accept') }}">@csrf<button class="button button-primary">Accept / Start serving</button></form>
                <form method="POST" action="{{ route('staff.skip') }}">@csrf<input type="hidden" name="reason" value="Skipped by staff"><button class="button button-light">Skip ticket</button></form>
            @elseif($current?->status === 'serving')
                <form method="POST" action="{{ route('staff.complete') }}">@csrf<button class="button button-primary">Complete customer</button></form>
            @else
                <form method="POST" action="{{ route('staff.next') }}">@csrf<button class="button button-primary" id="request-next" @disabled(! $session || $session->status !== 'open' || ! $staff->serviceDesk?->is_active)>Request next</button></form>
            @endif
        </div>
        @unless($staff->serviceDesk?->is_active)
            <p class="form-note">Your desk is inactive or unassigned. Contact an administrator.</p>
        @endunless
    </div>
    <div class="staff-stats">
        <div class="stat-card"><span>Waiting customers</span><strong id="waiting-count">{{ $waitingCount }}</strong><small>In the current session</small></div>
        <div class="stat-card"><span>Customers served</span><strong>{{ $servedCount }}</strong><small>This session</small></div>
        <div class="stat-card"><span>Queue session</span><strong id="staff-session">{{ $session?->label ?? '—' }}</strong><small>{{ $session?->status ?? 'No session' }}</small></div>
    </div>
</section>
@push('scripts')
<script>
(() => {
    const url = @json(route('staff.state'));
    const initialTicket = @json($current ? ['number' => $current->ticket_number, 'status' => $current->status] : null);
    const initialSessionStatus = @json($session?->status ?? 'closed');
    async function refresh() {
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) return;
            const state = await response.json();
            if ((state.ticket?.number || null) !== (initialTicket?.number || null)
                || (state.ticket?.status || null) !== (initialTicket?.status || null)
                || state.session_status !== initialSessionStatus) {
                window.location.reload();
                return;
            }
            document.getElementById('waiting-count').textContent = state.waiting_count;
            document.getElementById('session-status').textContent = state.session_status;
            const statusText = document.querySelector('#availability span:last-child');
            if (statusText) statusText.textContent = state.ticket ? `Handling ${state.ticket.number}` : (state.session_status === 'open' ? 'Available' : 'Session closed');
            const requestNext = document.getElementById('request-next');
            if (requestNext) requestNext.disabled = Boolean(state.ticket) || state.session_status !== 'open';
        } catch {}
    }
    window.setInterval(refresh, 4000);
})();
</script>
@endpush
@endsection
