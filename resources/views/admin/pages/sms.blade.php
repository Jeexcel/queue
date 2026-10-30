<section class="panel">
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
