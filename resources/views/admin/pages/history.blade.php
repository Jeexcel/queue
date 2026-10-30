<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">TICKET HISTORY</span><h2>Issued tickets</h2></div></div>
    <div class="table-wrap"><table><thead><tr><th>Ticket</th><th>Session</th><th>Status</th><th>Desk / staff</th><th>Issued</th></tr></thead><tbody>
        @forelse($tickets as $ticket)
            <tr><td><strong>{{ $ticket->ticket_number }}</strong></td><td>{{ $ticket->queueSession->label }}</td><td><span class="status-tag">{{ $ticket->status }}</span></td><td>{{ $ticket->serviceDesk?->name ?? '—' }}<small>{{ $ticket->staff?->name ?? '' }}</small></td><td>{{ $ticket->issued_at->format('M j, Y · g:i A') }}</td></tr>
        @empty<tr><td colspan="5" class="empty-note">No tickets have been issued.</td></tr>@endforelse
    </tbody></table></div>
    {{ $tickets->links() }}
</section>
<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">TICKET ACTIVITY</span><h2>Recent lifecycle events</h2></div></div>
    <div class="table-wrap"><table><thead><tr><th>Time</th><th>Ticket</th><th>Event</th><th>Staff</th><th>Desk</th><th>Reason</th></tr></thead><tbody>
        @forelse($ticketEvents as $event)<tr><td>{{ $event->created_at->format('M j · H:i:s') }}</td><td>{{ $event->ticket->ticket_number }}<small>{{ $event->ticket->queueSession->label }}</small></td><td>{{ str_replace('_', ' ', $event->event_type) }}</td><td>{{ $event->staff?->name ?? '—' }}</td><td>{{ $event->serviceDesk?->name ?? '—' }}</td><td>{{ $event->reason ?? '—' }}</td></tr>@empty<tr><td colspan="6" class="empty-note">No ticket events recorded.</td></tr>@endforelse
    </tbody></table></div>
</section>
