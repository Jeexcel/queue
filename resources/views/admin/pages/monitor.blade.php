<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">LIVE QUEUE</span><h2>Current and recoverable tickets</h2></div><a href="{{ route('display') }}" class="text-link">Open waiting-room display →</a></div>
    <div class="table-wrap"><table><thead><tr><th>Ticket</th><th>Session</th><th>Status</th><th>Desk / staff</th><th>Recovery</th></tr></thead><tbody>
        @forelse($tickets as $ticket)
            <tr><td><strong>{{ $ticket->ticket_number }}</strong><small>{{ $ticket->issued_at->format('M j · H:i') }}</small></td><td>{{ $ticket->queueSession->label }}</td><td><span class="status-tag">{{ $ticket->status }}</span></td><td>{{ $ticket->serviceDesk?->name ?? '—' }}<small>{{ $ticket->staff?->name ?? '' }}</small></td>
                <td>@if(in_array($ticket->status, ['called', 'serving'], true))<form method="POST" action="{{ route('admin.tickets.recover', $ticket) }}" onsubmit="return confirm('Return this active ticket to the waiting queue?')">@csrf<input name="reason" required value="Admin recovery" aria-label="Recovery reason"><button class="button button-light button-small">Recover</button></form>@elseif($ticket->status === 'skipped')<form method="POST" action="{{ route('admin.tickets.recall', $ticket) }}">@csrf<button class="button button-light button-small">Recall to queue</button></form>@else — @endif</td></tr>
        @empty<tr><td colspan="5" class="empty-note">No tickets need monitoring or recovery.</td></tr>@endforelse
    </tbody></table></div>
    {{ $tickets->links() }}
</section>
