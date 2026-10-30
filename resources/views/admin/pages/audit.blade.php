<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">AUDIT LOG</span><h2>Recent administrative actions</h2></div></div>
    <div class="table-wrap"><table><thead><tr><th>Time</th><th>Administrator</th><th>Action</th><th>Details</th></tr></thead><tbody>
        @forelse($audits as $entry)<tr><td>{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('M j · H:i:s') }}</td><td>{{ $entry->user_name ?? 'System' }}</td><td>{{ $entry->action }}</td><td>{{ $entry->metadata }}</td></tr>@empty<tr><td colspan="4" class="empty-note">No administrative actions recorded.</td></tr>@endforelse
    </tbody></table></div>
</section>
