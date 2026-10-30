<section class="panel session-panel">
    <div class="panel-heading"><div><span class="eyebrow">CURRENT SESSION</span><h2>{{ $session?->label ?? 'No active session' }}</h2></div><span class="status-tag">{{ $session?->status ?? 'closed' }}</span></div>
    @if($session)
        <p>Started {{ $session->started_at->format('M j, Y · g:i A') }}. New tickets and staff assignments are enabled.</p>
        <form method="POST" action="{{ route('admin.session.end') }}" onsubmit="return confirm('End this queue session? Waiting tickets and active assignments will be preserved for history.')">@csrf<button class="button button-danger">End session</button></form>
    @else
        <p>Starting a session opens ticket collection and staff assignment. Previous session records remain available.</p>
        <form method="POST" action="{{ route('admin.session.start') }}" class="inline-fields">@csrf<label>Session name<input name="label" required maxlength="120" placeholder="e.g. Morning session"></label><button class="button button-primary">Start session</button></form>
    @endif
</section>
<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">SESSION HISTORY</span><h2>Recent sessions</h2></div></div>
    <div class="session-list">@forelse($sessions as $item)<div><strong>{{ $item->label }}</strong><span>{{ ucfirst($item->status) }} · {{ $item->tickets_count }} tickets</span><small>{{ $item->started_at->format('M j, Y · g:i A') }}</small></div>@empty<p class="empty-note">No queue sessions yet.</p>@endforelse</div>
</section>
