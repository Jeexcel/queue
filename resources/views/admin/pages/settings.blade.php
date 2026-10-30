<section class="panel">
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
