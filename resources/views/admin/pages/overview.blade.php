<section class="admin-overview">
    <div class="stat-card"><span>Active service desks</span><strong>{{ $activeDeskCount }}</strong><small>Configured desks ready to serve</small></div>
    <div class="stat-card"><span>Active staff</span><strong>{{ $activeStaffCount }}</strong><small>Staff accounts enabled</small></div>
    <div class="stat-card"><span>Waiting tickets</span><strong>{{ $waitingCount }}</strong><small>{{ $session ? 'Current session' : 'No open session' }}</small></div>
</section>
<section class="panel admin-shortcuts">
    <div class="panel-heading"><div><span class="eyebrow">ADMIN TOOLS</span><h2>Go to a section</h2></div></div>
    <div class="admin-shortcut-grid">
        <a href="{{ route('admin.sessions') }}"><strong>Queue sessions</strong><span>Start or end a queue session and review past sessions.</span></a>
        <a href="{{ route('admin.monitor') }}"><strong>Queue monitor</strong><span>Review active tickets and recover assignments.</span></a>
        <a href="{{ route('admin.desks.index') }}"><strong>Service desks</strong><span>Manage desk names, availability, and assignments.</span></a>
        <a href="{{ route('admin.staff.index') }}"><strong>Staff accounts</strong><span>Create accounts and update staff assignments.</span></a>
        <a href="{{ route('admin.history') }}"><strong>Ticket history</strong><span>Review past tickets, events, and sessions.</span></a>
        <a href="{{ route('admin.settings.page') }}"><strong>Queue settings</strong><span>Update organization and ticket configuration.</span></a>
        <a href="{{ route('admin.sms.index') }}"><strong>SMS settings</strong><span>Configure recovery messages and test delivery.</span></a>
    </div>
</section>
