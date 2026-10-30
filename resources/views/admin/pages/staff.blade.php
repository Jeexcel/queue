<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">STAFF ACCOUNTS</span><h2>Create a staff account</h2></div></div>
    <form method="POST" action="{{ route('admin.staff.create') }}" class="form-grid staff-create">@csrf
        <label>Full name<input name="name" required maxlength="255"></label><label>Email<input type="email" name="email" required></label>
        <label>Phone<input name="phone" required></label><label>Temporary password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
        <label>Confirm password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label>
        <label>Service desk<select name="service_desk_id"><option value="">Unassigned</option>@foreach($activeDesks as $desk)<option value="{{ $desk->id }}">{{ $desk->name }}</option>@endforeach</select></label>
        <button class="button button-primary">Create staff account</button>
    </form>
</section>
<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">STAFF DIRECTORY</span><h2>Staff and assignments</h2></div></div>
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
</section>
