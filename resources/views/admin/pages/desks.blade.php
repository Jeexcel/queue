<section class="panel">
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
</section>
