@extends('layouts.app')
@section('title', 'Queue administration')
@section('content')
<section class="page-heading">
    <div><div class="eyebrow">ADMINISTRATION</div><h1>Queue control room</h1><p>Manage sessions, desks, staff, and monitor queue activity.</p></div>
    <div class="availability-pill {{ $session ? '' : 'pill-muted' }}"><span class="live-dot"></span>{{ $session ? 'Queue session open' : 'No open session' }}</div>
</section>
<section class="admin-overview">
    <div class="panel session-panel">
        <div class="panel-heading"><div><span class="eyebrow">QUEUE SESSION</span><h2>{{ $session?->label ?? 'No active session' }}</h2></div><span class="status-tag">{{ $session?->status ?? 'closed' }}</span></div>
        @if($session)
            <p>Started {{ $session->started_at->format('M j, Y · g:i A') }}. New tickets and staff assignments are enabled.</p>
            <form method="POST" action="{{ route('admin.session.end') }}" onsubmit="return confirm('End this queue session? Waiting tickets and active assignments will be preserved for history.')">@csrf<button class="button button-danger">End session</button></form>
        @else
            <p>Starting a session opens ticket collection and staff assignment. Previous session records remain available.</p>
            <form method="POST" action="{{ route('admin.session.start') }}" class="inline-fields">@csrf<label>Session name<input name="label" required maxlength="120" placeholder="e.g. Morning session"></label><button class="button button-primary">Start session</button></form>
        @endif
    </div>
    <div class="stat-card"><span>Active service desks</span><strong>{{ $desks->where('is_active', true)->count() }}</strong><small>{{ $staff->where('is_active', true)->count() }} active staff</small></div>
    <div class="stat-card"><span>Waiting tickets</span><strong>{{ $session?->tickets()->where('status', 'waiting')->count() ?? 0 }}</strong><small>{{ $session ? 'Current session' : 'No open session' }}</small></div>
</section>
<section class="admin-grid">
    <div class="panel">
        <div class="panel-heading"><div><span class="eyebrow">SERVICE DESKS</span><h2>Desk availability</h2></div></div>
        <div class="desk-grid">
            @forelse($desks as $desk)
                @php($activeTicket = $activeStatusByDesk->get($desk->id))
                <article class="desk-card">
                    <div class="desk-card-top"><strong>{{ $desk->name }}</strong><span class="status-tag">{{ $desk->is_active ? ($activeTicket?->status ?? ($desk->staff->isNotEmpty() ? 'idle' : 'unassigned')) : 'inactive' }}</span></div>
                    <small>{{ $desk->staff->first()?->name ?? 'No active staff assigned' }}</small>
                    <form method="POST" action="{{ route('admin.desks.update', $desk) }}" class="desk-edit-form">@csrf @method('PUT')
                        <input name="name" value="{{ $desk->name }}" required aria-label="Desk name">
                        <input name="code" value="{{ $desk->code }}" placeholder="Code" aria-label="Desk code">
                        <input type="hidden" name="is_active" value="0">
                        <label class="check-label"><input type="checkbox" name="is_active" value="1" @checked($desk->is_active)> Active</label>
                        <button class="button button-light button-small">Save</button>
                    </form>
                </article>
            @empty
                <p class="empty-note">No service desks configured.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('admin.desks.create') }}" class="inline-fields add-desk">@csrf<label>New desk<input name="name" required maxlength="100" placeholder="Desk 5"></label><label>Code (optional)<input name="code" maxlength="32" placeholder="D5"></label><button class="button button-dark">Add desk</button></form>
    </div>
    <div class="panel">
        <div class="panel-heading"><div><span class="eyebrow">STAFF ACCOUNTS</span><h2>Staff and assignments</h2></div></div>
        <form method="POST" action="{{ route('admin.staff.create') }}" class="form-grid staff-create">@csrf
            <label>Full name<input name="name" required maxlength="255"></label><label>Email<input type="email" name="email" required></label>
            <label>Phone<input name="phone" required></label><label>Temporary password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
            <label>Confirm password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label>
            <label>Service desk<select name="service_desk_id"><option value="">Unassigned</option>@foreach($desks->where('is_active', true) as $desk)<option value="{{ $desk->id }}">{{ $desk->name }}</option>@endforeach</select></label>
            <button class="button button-primary">Create staff account</button>
        </form>
        <div class="table-wrap"><table><thead><tr><th>Staff member</th><th>Desk</th><th>Active</th><th>Save assignment</th></tr></thead><tbody>
            @forelse($staff as $member)
                <tr><td colspan="4"><form method="POST" action="{{ route('admin.staff.update', $member) }}" class="staff-update">@csrf @method('PUT')
                    <input name="name" value="{{ $member->name }}" required aria-label="Staff name">
                    <input type="email" name="email" value="{{ $member->email }}" required aria-label="Staff email">
                    <input name="phone" value="{{ $member->phone }}" aria-label="Staff phone">
                    <select name="service_desk_id"><option value="">Unassigned</option>@foreach($desks as $desk)<option value="{{ $desk->id }}" @selected($member->service_desk_id === $desk->id)>{{ $desk->name }}{{ $desk->is_active ? '' : ' (inactive)' }}</option>@endforeach</select>
                    <input type="hidden" name="is_active" value="0"><label class="check-label"><input type="checkbox" name="is_active" value="1" @checked($member->is_active)> Active</label>
                    <button class="button button-light button-small">Save</button>
                </form></td></tr>
            @empty<tr><td colspan="4" class="empty-note">No staff accounts yet.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</section>
<section class="panel settings-panel">
    <div class="panel-heading"><div><span class="eyebrow">TICKET CONFIGURATION</span><h2>Organization and queue behavior</h2></div></div>
    <form method="POST" action="{{ route('admin.settings') }}" class="form-grid settings-form">@csrf @method('PUT')
        <label>Organization name<input name="organization_name" value="{{ $settings->organization_name }}" required></label>
        <label>Organization logo URL<input type="url" name="logo_url" value="{{ $settings->logo_url }}" placeholder="https://..."></label>
        <label>Ticket prefix<input name="ticket_prefix" value="{{ $settings->ticket_prefix }}" required maxlength="8"></label>
        <label>Skipped ticket policy<select name="skip_policy"><option value="requeue" @selected($settings->skip_policy === 'requeue')>Requeue at the end of the waiting line</option><option value="manual_recall" @selected($settings->skip_policy === 'manual_recall')>Keep skipped for admin recovery</option></select></label>
        <label>Announcement language<input name="announcement_language" value="{{ $settings->announcement_language }}" placeholder="en-US"></label>
        <label>Preferred speech voice<input name="announcement_voice" value="{{ $settings->announcement_voice }}" placeholder="Leave blank for browser default"></label>
        <button class="button button-primary">Save configuration</button>
    </form>
</section>
<section class="panel settings-panel">
    <div class="panel-heading"><div><span class="eyebrow">SMS AND RECOVERY</span><h2>Provider settings</h2></div></div>
    <form method="POST" action="{{ route('admin.sms.settings') }}" class="form-grid settings-form">@csrf @method('PUT')
        <label>Provider<select name="provider"><option value="hubtel" @selected($sms->provider === 'hubtel')>Hubtel</option><option value="nalo" @selected($sms->provider === 'nalo')>Nalo</option></select></label>
        <label>Sender ID<input name="sender_id" value="{{ $sms->sender_id }}" maxlength="32"></label>
        <label>API endpoint<input type="url" name="endpoint" value="{{ $sms->endpoint }}" placeholder="Leave blank to use provider default"></label>
        <label>Username / client ID<input name="username" autocomplete="off" placeholder="Saved encrypted; leave blank to keep current"></label>
        <label>Password<input type="password" name="password" autocomplete="new-password" placeholder="Saved encrypted; leave blank to keep current"></label>
        <button class="button button-primary">Save SMS settings</button>
    </form>
    <form method="POST" action="{{ route('admin.sms.test') }}" class="inline-fields test-sms">@csrf<label>Send test to<input name="phone" required placeholder="+233..."></label><button class="button button-light">Test SMS</button><small>Provider errors are returned as an error and credentials are never displayed.</small></form>
</section>
<section class="admin-grid">
    <div class="panel"><div class="panel-heading"><div><span class="eyebrow">LIVE QUEUE</span><h2>Recent tickets</h2></div><a href="{{ route('display') }}" class="text-link">Open display →</a></div>
        <div class="table-wrap"><table><thead><tr><th>Ticket</th><th>Session</th><th>Status</th><th>Desk / staff</th><th>Recovery</th></tr></thead><tbody>
            @forelse($tickets as $ticket)
                <tr><td><strong>{{ $ticket->ticket_number }}</strong><small>{{ $ticket->issued_at->format('M j · H:i') }}</small></td><td>{{ $ticket->queueSession->label }}</td><td><span class="status-tag">{{ $ticket->status }}</span></td><td>{{ $ticket->serviceDesk?->name ?? '—' }}<small>{{ $ticket->staff?->name ?? '' }}</small></td>
                <td>@if(in_array($ticket->status, ['called', 'serving'], true))<form method="POST" action="{{ route('admin.tickets.recover', $ticket) }}" onsubmit="return confirm('Return this active ticket to the waiting queue?')">@csrf<input name="reason" required value="Admin recovery" aria-label="Recovery reason"><button class="button button-light button-small">Recover</button></form>@elseif($ticket->status === 'skipped')<form method="POST" action="{{ route('admin.tickets.recall', $ticket) }}">@csrf<button class="button button-light button-small">Recall to queue</button></form>@else — @endif</td></tr>
            @empty<tr><td colspan="5" class="empty-note">No tickets have been issued.</td></tr>@endforelse
        </tbody></table></div>
        {{ $tickets->links() }}
    </div>
    <div class="panel"><div class="panel-heading"><div><span class="eyebrow">SESSION HISTORY</span><h2>Recent sessions</h2></div></div>
        <div class="session-list">@forelse($sessions as $item)<div><strong>{{ $item->label }}</strong><span>{{ ucfirst($item->status) }} · {{ $item->tickets_count }} tickets</span><small>{{ $item->started_at->format('M j, Y · g:i A') }}</small></div>@empty<p class="empty-note">No queue sessions yet.</p>@endforelse</div>
    </div>
</section>
<section class="panel settings-panel"><div class="panel-heading"><div><span class="eyebrow">TICKET ACTIVITY</span><h2>Recent lifecycle events</h2></div></div>
    <div class="table-wrap"><table><thead><tr><th>Time</th><th>Ticket</th><th>Event</th><th>Staff</th><th>Desk</th><th>Reason</th></tr></thead><tbody>
        @forelse($ticketEvents as $event)<tr><td>{{ $event->created_at->format('M j · H:i:s') }}</td><td>{{ $event->ticket->ticket_number }}<small>{{ $event->ticket->queueSession->label }}</small></td><td>{{ str_replace('_', ' ', $event->event_type) }}</td><td>{{ $event->staff?->name ?? '—' }}</td><td>{{ $event->serviceDesk?->name ?? '—' }}</td><td>{{ $event->reason ?? '—' }}</td></tr>@empty<tr><td colspan="6" class="empty-note">No ticket events recorded.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="panel settings-panel"><div class="panel-heading"><div><span class="eyebrow">AUDIT LOG</span><h2>Recent administrative actions</h2></div></div>
    <div class="table-wrap"><table><thead><tr><th>Time</th><th>Administrator</th><th>Action</th><th>Details</th></tr></thead><tbody>
        @forelse($audits as $entry)<tr><td>{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('M j · H:i:s') }}</td><td>{{ $entry->user_name ?? 'System' }}</td><td>{{ $entry->action }}</td><td>{{ $entry->metadata }}</td></tr>@empty<tr><td colspan="4" class="empty-note">No administrative actions recorded.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
