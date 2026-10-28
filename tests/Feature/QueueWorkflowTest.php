<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\QueueSession;
use App\Models\QueueSetting;
use App\Models\ServiceDesk;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_customer_can_collect_a_session_scoped_ticket_and_retry_idempotently(): void
    {
        $desk = ServiceDesk::query()->create(['name' => 'Desk 1', 'is_active' => true]);
        $this->openSession();
        $key = (string) Str::uuid();

        $response = $this->post('/tickets', ['request_key' => $key]);
        $response->assertOk()->assertSee('A001')->assertSee('Please wait for your number');
        $this->post('/tickets', ['request_key' => $key])->assertOk()->assertSee('A001');
        $this->post('/tickets', ['request_key' => (string) Str::uuid()])->assertOk()->assertSee('A002');

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame('waiting', $ticket->status);
        $this->assertNull($ticket->service_desk_id);
        $this->assertSame(2, Ticket::query()->count());
        $this->assertSame('issued', TicketEvent::query()->firstOrFail()->event_type);
        $this->assertNotNull($desk);
    }

    public function test_collection_is_closed_without_an_open_session_and_after_end(): void
    {
        $this->get('/')->assertOk()->assertSee('Ticket collection is unavailable');
        $this->post('/tickets', ['request_key' => (string) Str::uuid()])->assertRedirect('/');

        $this->openSession();
        app(QueueService::class)->endSession(User::factory()->create(['role' => 'admin']));
        $this->post('/tickets', ['request_key' => (string) Str::uuid()])->assertRedirect('/');
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_staff_claims_accepts_and_completes_an_owned_ticket(): void
    {
        $desk = ServiceDesk::query()->create(['name' => 'Desk 2', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'service_desk_id' => $desk->id]);
        $session = $this->openSession();
        $ticket = $this->issue($session->id, 1);
        $this->actingAs($staff);

        $this->post('/staff/next')->assertRedirect(route('staff.index'));
        $ticket->refresh();
        $this->assertSame('called', $ticket->status);
        $this->assertSame($staff->id, $ticket->assigned_staff_id);
        $this->assertSame($desk->id, $ticket->service_desk_id);
        $this->assertSame(1, Announcement::query()->count());

        $this->post('/staff/accept')->assertRedirect(route('staff.index'));
        $this->assertSame('serving', $ticket->refresh()->status);
        $this->getJson('/display/state?cursor=1')->assertJsonPath('current.ticket', 'A001')
            ->assertJsonPath('announcements', []);
        $this->post('/staff/complete')->assertRedirect(route('staff.index'));
        $this->assertSame('completed', $ticket->refresh()->status);
        $this->getJson('/display/state?cursor=1')->assertJsonPath('current', null);
        $this->assertSame(['called', 'serving', 'completed'], TicketEvent::query()->orderBy('id')->pluck('event_type')->all());
    }

    public function test_staff_cannot_claim_a_second_ticket_while_handling_a_customer(): void
    {
        $desk = ServiceDesk::query()->create(['name' => 'Desk 3', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'service_desk_id' => $desk->id]);
        $session = $this->openSession();
        $this->issue($session->id, 1);
        $this->issue($session->id, 2);
        $this->actingAs($staff);

        $this->post('/staff/next')->assertRedirect(route('staff.index'));
        $this->post('/staff/next')->assertSessionHasErrors('ticket');
        $this->assertSame(1, Ticket::query()->where('status', 'called')->count());
    }

    public function test_different_staff_members_claim_different_waiting_tickets(): void
    {
        $deskOne = ServiceDesk::query()->create(['name' => 'Desk 1', 'is_active' => true]);
        $deskTwo = ServiceDesk::query()->create(['name' => 'Desk 2', 'is_active' => true]);
        $staffOne = User::factory()->create(['role' => 'staff', 'service_desk_id' => $deskOne->id]);
        $staffTwo = User::factory()->create(['role' => 'staff', 'service_desk_id' => $deskTwo->id]);
        $session = $this->openSession();
        $first = $this->issue($session->id, 1);
        $second = $this->issue($session->id, 2);

        $this->actingAs($staffOne)->post('/staff/next')->assertRedirect(route('staff.index'));
        $this->actingAs($staffTwo)->post('/staff/next')->assertRedirect(route('staff.index'));

        $this->assertSame('called', $first->refresh()->status);
        $this->assertSame($staffOne->id, $first->assigned_staff_id);
        $this->assertSame('called', $second->refresh()->status);
        $this->assertSame($staffTwo->id, $second->assigned_staff_id);
        $this->assertSame(2, Announcement::query()->count());
    }

    public function test_skip_is_recorded_and_requeues_without_retaining_assignment(): void
    {
        $desk = ServiceDesk::query()->create(['name' => 'Desk 4', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'service_desk_id' => $desk->id]);
        $session = $this->openSession();
        $ticket = $this->issue($session->id, 1);
        $nextTicket = $this->issue($session->id, 2);
        $this->actingAs($staff)->post('/staff/next');

        $this->post('/staff/skip', ['reason' => 'Customer not present'])->assertRedirect(route('staff.index'));
        $ticket->refresh();
        $this->assertSame('waiting', $ticket->status);
        $this->assertNull($ticket->assigned_staff_id);
        $this->assertSame(3, (int) $ticket->queue_order);
        $this->assertSame(2, (int) $nextTicket->queue_order);
        $this->assertSame(['called', 'skipped', 'requeued'], TicketEvent::query()->orderBy('id')->pluck('event_type')->all());
        $this->post('/staff/next')->assertRedirect(route('staff.index'))->assertSessionHas('status', 'Now calling A002.');
        $this->assertSame('called', $nextTicket->refresh()->status);
    }

    public function test_display_is_read_only_and_initial_connection_does_not_replay_call_history(): void
    {
        $desk = ServiceDesk::query()->create(['name' => 'Desk 1', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'service_desk_id' => $desk->id]);
        $session = $this->openSession();
        $this->issue($session->id, 1);
        $this->actingAs($staff)->post('/staff/next');

        $this->get('/display')->assertOk()->assertSee('Enable sound')->assertSee('Test sound');
        $this->getJson('/display/state')->assertOk()
            ->assertJsonPath('current.ticket', 'A001')
            ->assertJsonPath('current.desk', 'Desk 1')
            ->assertJsonPath('announcements', [])
            ->assertJsonPath('waiting_count', 0);
        $this->getJson('/display/state?cursor=0')->assertJsonCount(1, 'announcements');
    }

    public function test_role_middleware_keeps_staff_and_admin_surfaces_separate(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertSame('staff', $staff->fresh()->role);
        $this->assertTrue($staff->fresh()->is_active);
        $this->assertSame('admin', $admin->fresh()->role);

        $this->actingAs($staff)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/staff')->assertForbidden();
        $staffResponse = $this->actingAs($staff)->get('/staff');
        $staffResponse->assertOk();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_session_end_preserves_active_ticket_and_prevents_new_claims(): void
    {
        $desk = ServiceDesk::query()->create(['name' => 'Desk 5', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'staff', 'service_desk_id' => $desk->id]);
        $session = $this->openSession();
        $ticket = $this->issue($session->id, 1);
        $this->actingAs($staff)->post('/staff/next');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(QueueService::class)->endSession(auth()->user());

        $this->assertSame('called', $ticket->refresh()->status);
        $this->assertSame($staff->id, $ticket->assigned_staff_id);
        $this->actingAs($staff)->post('/staff/next')->assertSessionHasErrors('ticket');
    }

    public function test_admin_can_create_and_edit_staff_without_sharing_an_exclusive_desk(): void
    {
        $deskOne = ServiceDesk::query()->create(['name' => 'Desk 1', 'is_active' => true]);
        $deskTwo = ServiceDesk::query()->create(['name' => 'Desk 2', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Queue Staff',
            'email' => 'queue-staff@example.test',
            'phone' => '+233200000010',
            'password' => 'a-strong-staff-password',
            'password_confirmation' => 'a-strong-staff-password',
            'service_desk_id' => $deskOne->id,
        ])->assertRedirect(route('admin.index'));

        $staff = User::query()->where('email', 'queue-staff@example.test')->firstOrFail();
        $this->put("/admin/staff/{$staff->id}", [
            'name' => 'Updated Queue Staff',
            'email' => 'updated-staff@example.test',
            'phone' => '+233200000011',
            'service_desk_id' => $deskTwo->id,
            'is_active' => '1',
        ])->assertRedirect(route('admin.index'));
        $this->assertSame('Updated Queue Staff', $staff->fresh()->name);
        $this->assertSame($deskTwo->id, $staff->fresh()->service_desk_id);

        $this->post('/admin/staff', [
            'name' => 'Another Staff',
            'email' => 'another-staff@example.test',
            'phone' => '+233200000012',
            'password' => 'another-strong-password',
            'password_confirmation' => 'another-strong-password',
            'service_desk_id' => $deskTwo->id,
        ])->assertStatus(422);
        $this->assertSame(1, User::query()->where('service_desk_id', $deskTwo->id)->where('is_active', true)->count());
    }

    public function test_starting_a_session_when_another_is_open_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        app(QueueService::class)->startSession($admin, 'First');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(QueueService::class)->startSession($admin, 'Second');
    }

    private function openSession(): \App\Models\QueueSession
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return app(QueueService::class)->startSession($admin, 'Morning session');
    }

    private function issue(int $sessionId, int $sequence): Ticket
    {
        $session = QueueSession::query()->findOrFail($sessionId);
        $session->update([
            'next_sequence' => max((int) $session->next_sequence, $sequence + 1),
            'next_queue_order' => max((int) $session->next_queue_order, $sequence + 1),
        ]);

        return Ticket::query()->create([
            'queue_session_id' => $sessionId,
            'sequence_number' => $sequence,
            'queue_order' => $sequence,
            'ticket_number' => 'A'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'status' => 'waiting',
            'issued_at' => now(),
        ]);
    }
}
