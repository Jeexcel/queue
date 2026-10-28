<?php

namespace App\Services;

use App\Events\TicketCalled;
use App\Models\Announcement;
use App\Models\QueueSession;
use App\Models\QueueSetting;
use App\Models\ServiceDesk;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QueueService
{
    public function issueTicket(string $requestKey): Ticket
    {
        return DB::transaction(function () use ($requestKey) {
            $control = DB::table('queue_control')->where('id', 1)->lockForUpdate()->first();
            $existing = Ticket::query()->where('request_key', $requestKey)->first();

            if ($existing) {
                return $existing;
            }

            $session = $control?->active_session_id
                ? QueueSession::query()->whereKey($control->active_session_id)->lockForUpdate()->first()
                : null;

            if (! $session || $session->status !== 'open') {
                throw ValidationException::withMessages(['queue' => 'Ticket collection is currently closed.']);
            }

            $sequence = (int) $session->next_sequence;
            $queueOrder = (int) $session->next_queue_order;
            $session->update(['next_sequence' => $sequence + 1, 'next_queue_order' => $queueOrder + 1]);
            $ticket = Ticket::query()->create([
                'queue_session_id' => $session->id,
                'sequence_number' => $sequence,
                'queue_order' => $queueOrder,
                'ticket_number' => $session->ticket_prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'request_key' => $requestKey,
                'status' => 'waiting',
                'issued_at' => now(),
            ]);
            $this->record($ticket, 'issued');

            return $ticket;
        }, 3);
    }

    public function startSession(User $admin, string $label): QueueSession
    {
        return DB::transaction(function () use ($admin, $label) {
            $control = DB::table('queue_control')->where('id', 1)->lockForUpdate()->first();
            if ($control?->active_session_id) {
                $active = QueueSession::query()->find($control->active_session_id);
                if ($active?->status === 'open') {
                    throw ValidationException::withMessages(['session' => 'End the current queue session before starting another.']);
                }
            }

            $settings = QueueSetting::query()->firstOrFail();
            $session = QueueSession::query()->create([
                'label' => $label,
                'status' => 'open',
                'ticket_prefix' => $settings->ticket_prefix,
                'started_by' => $admin->id,
                'started_at' => now(),
            ]);
            DB::table('queue_control')->where('id', 1)->update([
                'active_session_id' => $session->id,
                'updated_at' => now(),
            ]);
            $this->audit($admin, 'session.started', ['session_id' => $session->id, 'label' => $label]);

            return $session;
        }, 3);
    }

    public function endSession(User $admin): void
    {
        DB::transaction(function () use ($admin) {
            $control = DB::table('queue_control')->where('id', 1)->lockForUpdate()->first();
            $session = $control?->active_session_id
                ? QueueSession::query()->whereKey($control->active_session_id)->lockForUpdate()->first()
                : null;
            if (! $session || $session->status !== 'open') {
                throw ValidationException::withMessages(['session' => 'There is no open session to end.']);
            }

            $session->update(['status' => 'ended', 'ended_at' => now(), 'ended_by' => $admin->id]);
            DB::table('queue_control')->where('id', 1)->update(['active_session_id' => null, 'updated_at' => now()]);
            $this->audit($admin, 'session.ended', [
                'session_id' => $session->id,
                'waiting_tickets_preserved' => $session->tickets()->where('status', 'waiting')->count(),
                'active_tickets_preserved' => $session->tickets()->whereIn('status', ['called', 'serving'])->count(),
            ]);
        }, 3);
    }

    public function claimNext(User $staff): ?Ticket
    {
        return DB::transaction(function () use ($staff) {
            $staff = User::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            if (! $staff->is_active || $staff->role !== 'staff') {
                throw ValidationException::withMessages(['staff' => 'This staff account is not active.']);
            }
            if (Ticket::query()->where('assigned_staff_id', $staff->id)->whereIn('status', ['called', 'serving'])->exists()) {
                throw ValidationException::withMessages(['ticket' => 'Complete or skip your current customer before requesting another.']);
            }
            if (! $staff->service_desk_id) {
                throw ValidationException::withMessages(['desk' => 'Ask an administrator to assign you to an active service desk.']);
            }

            $desk = ServiceDesk::query()->whereKey($staff->service_desk_id)->lockForUpdate()->first();
            if (! $desk?->is_active) {
                throw ValidationException::withMessages(['desk' => 'Your assigned service desk is unavailable.']);
            }
            $control = DB::table('queue_control')->where('id', 1)->lockForUpdate()->first();
            $session = $control?->active_session_id
                ? QueueSession::query()->whereKey($control->active_session_id)->lockForUpdate()->first()
                : null;
            if (! $session || $session->status !== 'open') {
                throw ValidationException::withMessages(['session' => 'The queue session is closed.']);
            }

            $ticket = Ticket::query()
                ->where('queue_session_id', $session->id)
                ->where('status', 'waiting')
                ->orderBy('queue_order')
                ->orderBy('sequence_number')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => 'called',
                'assigned_staff_id' => $staff->id,
                'service_desk_id' => $desk->id,
                'called_at' => now(),
            ]);
            $this->record($ticket, 'called', $staff, $desk);
            $announcement = Announcement::query()->create([
                'event_uuid' => (string) Str::uuid(),
                'ticket_id' => $ticket->id,
                'service_desk_id' => $desk->id,
                'event_type' => 'ticket_called',
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => TicketCalled::dispatch($announcement));

            return $ticket->load(['staff', 'serviceDesk']);
        }, 3);
    }

    public function accept(User $staff): Ticket
    {
        return $this->transitionOwnedTicket($staff, 'called', 'serving', function (Ticket $ticket) use ($staff) {
            $ticket->update(['status' => 'serving', 'accepted_at' => now()]);
            $this->record($ticket, 'serving', $staff, $ticket->serviceDesk);
        });
    }

    public function complete(User $staff): Ticket
    {
        return $this->transitionOwnedTicket($staff, 'serving', 'completed', function (Ticket $ticket) use ($staff) {
            $ticket->update(['status' => 'completed', 'completed_at' => now()]);
            $this->record($ticket, 'completed', $staff, $ticket->serviceDesk);
        });
    }

    public function skip(User $staff, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($staff, $reason) {
            User::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $ticket = Ticket::query()->where('assigned_staff_id', $staff->id)->where('status', 'called')->lockForUpdate()->first();
            if (! $ticket) {
                throw ValidationException::withMessages(['ticket' => 'Only your currently called ticket can be skipped.']);
            }

            $previousDeskId = $ticket->service_desk_id;
            $this->record($ticket, 'skipped', $staff, $previousDeskId ? ServiceDesk::query()->find($previousDeskId) : null, $reason);
            $policy = QueueSetting::query()->value('skip_policy') ?? 'requeue';
            $queueOrder = $policy === 'requeue' ? $this->nextQueueOrder($ticket) : $ticket->queue_order;
            $ticket->update([
                'status' => $policy === 'requeue' ? 'waiting' : 'skipped',
                'assigned_staff_id' => null,
                'service_desk_id' => null,
                'queue_order' => $queueOrder,
                'called_at' => $policy === 'requeue' ? null : $ticket->called_at,
                'accepted_at' => $policy === 'requeue' ? null : $ticket->accepted_at,
            ]);
            if ($policy === 'requeue') {
                $this->record($ticket, 'requeued', $staff, $previousDeskId ? ServiceDesk::query()->find($previousDeskId) : null, $reason);
            }

            return $ticket->refresh();
        }, 3);
    }

    public function recover(Ticket $ticket, User $admin, string $reason): void
    {
        DB::transaction(function () use ($ticket, $admin, $reason) {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['called', 'serving'], true)) {
                throw ValidationException::withMessages(['ticket' => 'Only called or serving tickets can be recovered.']);
            }
            $desk = $locked->service_desk_id ? ServiceDesk::query()->find($locked->service_desk_id) : null;
            $locked->update([
                'status' => 'waiting',
                'assigned_staff_id' => null,
                'service_desk_id' => null,
                'queue_order' => $this->nextQueueOrder($locked),
                'called_at' => null,
                'accepted_at' => null,
            ]);
            $this->record($locked, 'admin_recovered', $admin, $desk, $reason);
            $this->audit($admin, 'ticket.recovered', ['ticket_id' => $locked->id, 'reason' => $reason]);
        }, 3);
    }

    public function recallSkipped(Ticket $ticket, User $admin): void
    {
        DB::transaction(function () use ($ticket, $admin) {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'skipped') {
                throw ValidationException::withMessages(['ticket' => 'Only skipped tickets can be recalled.']);
            }
            $locked->update(['status' => 'waiting', 'queue_order' => $this->nextQueueOrder($locked)]);
            $this->record($locked, 'admin_recalled', $admin);
            $this->audit($admin, 'ticket.recalled', ['ticket_id' => $locked->id]);
        }, 3);
    }

    private function transitionOwnedTicket(User $staff, string $from, string $to, callable $transition): Ticket
    {
        return DB::transaction(function () use ($staff, $from, $to, $transition) {
            User::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $ticket = Ticket::query()->where('assigned_staff_id', $staff->id)->where('status', $from)->lockForUpdate()->first();
            if (! $ticket) {
                throw ValidationException::withMessages(['ticket' => "No {$from} ticket is assigned to you."]);
            }
            $transition($ticket);
            return $ticket->refresh()->load(['staff', 'serviceDesk']);
        }, 3);
    }

    private function record(Ticket $ticket, string $type, ?User $staff = null, ?ServiceDesk $desk = null, ?string $reason = null): void
    {
        TicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'staff_id' => $staff?->id,
            'service_desk_id' => $desk?->id,
            'event_type' => $type,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function nextQueueOrder(Ticket $ticket): int
    {
        $session = QueueSession::query()->whereKey($ticket->queue_session_id)->lockForUpdate()->firstOrFail();
        $order = (int) $session->next_queue_order;
        $session->increment('next_queue_order');

        return $order;
    }

    private function audit(User $user, string $action, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
