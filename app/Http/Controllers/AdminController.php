<?php

namespace App\Http\Controllers;

use App\Models\QueueSession;
use App\Models\QueueSetting;
use App\Models\ServiceDesk;
use App\Models\SmsSetting;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\QueueService;
use App\Services\Sms\SmsProviderManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $section = $request->route('section', 'overview');
        $data = match ($section) {
            'overview' => $this->overviewData(),
            'sessions' => $this->sessionData(),
            'desks' => $this->deskData(),
            'staff' => $this->staffData(),
            'settings' => ['settings' => QueueSetting::query()->firstOrFail()],
            'sms' => ['sms' => SmsSetting::query()->firstOrFail()],
            'monitor' => ['tickets' => $this->tickets(['waiting', 'called', 'serving', 'skipped'])],
            'history' => $this->historyData(),
            'audit' => ['audits' => $this->auditEntries()],
            default => abort(404),
        };

        return view('admin', ['section' => $section] + $data);
    }

    public function startSession(Request $request, QueueService $queue): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);
        $queue->startSession($request->user(), $data['label']);

        return redirect()->route('admin.sessions')->with('status', 'Queue session started.');
    }

    public function endSession(Request $request, QueueService $queue): RedirectResponse
    {
        $queue->endSession($request->user());

        return redirect()->route('admin.sessions')->with('status', 'Session ended. Existing tickets and assignments remain in history.');
    }

    public function createDesk(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'code' => ['nullable', 'string', 'max:32', 'unique:service_desks,code']]);
        $desk = ServiceDesk::query()->create($data + ['is_active' => true]);
        $this->audit($request, 'desk.created', ['desk_id' => $desk->id]);

        return redirect()->route('admin.desks.index')->with('status', 'Service desk created.');
    }

    public function updateDesk(Request $request, ServiceDesk $desk): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('service_desks', 'code')->ignore($desk->id)],
            'is_active' => ['required', 'boolean'],
        ]);
        if (! $data['is_active'] && Ticket::query()->where('service_desk_id', $desk->id)->whereIn('status', ['called', 'serving'])->exists()) {
            return back()->withErrors(['desk' => 'Recover active tickets at this desk before deactivating it.']);
        }
        $desk->update($data);
        $this->audit($request, 'desk.updated', ['desk_id' => $desk->id, 'is_active' => $desk->is_active]);

        return redirect()->route('admin.desks.index')->with('status', 'Service desk updated.');
    }

    public function createStaff(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'service_desk_id' => ['nullable', 'exists:service_desks,id'],
        ]);
        $deskId = $data['service_desk_id'] ?: null;
        DB::transaction(function () use ($data, $deskId, $request) {
            if ($deskId) {
                $desk = ServiceDesk::query()->whereKey($deskId)->lockForUpdate()->firstOrFail();
                abort_unless($desk->is_active, 422, 'Only active desks can be assigned.');
                $this->assertDeskAvailable($deskId);
            }
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => 'staff',
                'is_active' => true,
                'service_desk_id' => $deskId,
            ]);
            $this->audit($request, 'staff.created', ['staff_id' => $user->id, 'service_desk_id' => $deskId]);
        }, 3);

        return redirect()->route('admin.staff.index')->with('status', 'Staff account created.');
    }

    public function updateStaff(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($staff->id)],
            'service_desk_id' => ['nullable', 'exists:service_desks,id'],
            'is_active' => ['required', 'boolean'],
        ]);
        $deskId = $data['service_desk_id'] ?: null;
        DB::transaction(function () use ($staff, $data, $deskId, $request) {
            $lockedStaff = User::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            if ($lockedStaff->service_desk_id !== $deskId || ! $data['is_active']) {
                if (Ticket::query()->where('assigned_staff_id', $staff->id)->whereIn('status', ['called', 'serving'])->exists()) {
                    abort(422, 'Recover this staff member’s active ticket before changing their assignment or disabling the account.');
                }
            }
            if ($deskId) {
                $desk = ServiceDesk::query()->whereKey($deskId)->lockForUpdate()->firstOrFail();
                abort_unless($desk->is_active, 422, 'Only active desks can be assigned.');
                $this->assertDeskAvailable($deskId, $staff->id);
            }
            $lockedStaff->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'service_desk_id' => $deskId,
                'is_active' => $data['is_active'],
            ]);
            $this->audit($request, 'staff.updated', ['staff_id' => $staff->id, 'service_desk_id' => $deskId, 'is_active' => (bool) $data['is_active']]);
        }, 3);

        return redirect()->route('admin.staff.index')->with('status', 'Staff assignment updated.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:120'],
            'logo_url' => ['nullable', 'url:http,https', 'max:2048'],
            'ticket_prefix' => ['required', 'alpha_num', 'max:8'],
            'skip_policy' => ['required', Rule::in(['requeue', 'manual_recall'])],
            'announcement_language' => ['required', 'string', 'max:16'],
            'announcement_voice' => ['nullable', 'string', 'max:120'],
        ]);
        QueueSetting::query()->firstOrFail()->update($data);
        $this->audit($request, 'queue.settings_updated', ['ticket_prefix' => $data['ticket_prefix'], 'skip_policy' => $data['skip_policy']]);

        return redirect()->route('admin.settings.page')->with('status', 'Queue settings saved. The prefix applies to new sessions.');
    }

    public function updateSmsSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['hubtel', 'nalo'])],
            'sender_id' => ['nullable', 'string', 'max:32'],
            'endpoint' => ['nullable', 'url:https', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);
        $setting = SmsSetting::query()->firstOrFail();
        $credentials = $setting->credentials ?? [];
        foreach (['username', 'password'] as $key) {
            if (filled($data[$key] ?? null)) {
                $credentials[$key] = $data[$key];
            }
        }
        $setting->update([
            'provider' => $data['provider'],
            'sender_id' => $data['sender_id'] ?? null,
            'endpoint' => $data['endpoint'] ?? null,
            'credentials' => $credentials,
        ]);
        $this->audit($request, 'sms.settings_updated', ['provider' => $setting->provider]);

        return redirect()->route('admin.sms.index')->with('status', 'SMS settings saved. Secret fields are encrypted and omitted from responses.');
    }

    public function testSms(Request $request, SmsProviderManager $providers): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        try {
            $providers->send($data['phone'], 'Queue Management SMS test.');
        } catch (ConnectionException|RequestException|\RuntimeException $exception) {
            Log::warning('SMS test delivery failed.', ['provider_error' => class_basename($exception)]);

            return back()->withErrors(['sms' => 'The provider could not deliver the test message. Check the provider settings and try again.']);
        }
        $this->audit($request, 'sms.test_sent', ['phone_suffix' => substr($data['phone'], -4)]);

        return redirect()->route('admin.sms.index')->with('status', 'Test message sent.');
    }

    public function recoverTicket(Request $request, Ticket $ticket, QueueService $queue): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $queue->recover($ticket, $request->user(), $data['reason']);

        return redirect()->route('admin.monitor')->with('status', "{$ticket->ticket_number} was recovered and returned to the waiting queue.");
    }

    public function recallSkippedTicket(Request $request, Ticket $ticket, QueueService $queue): RedirectResponse
    {
        $queue->recallSkipped($ticket, $request->user());

        return redirect()->route('admin.monitor')->with('status', "{$ticket->ticket_number} was returned to the waiting queue.");
    }

    private function overviewData(): array
    {
        $session = $this->activeSession();

        return [
            'session' => $session,
            'activeDeskCount' => ServiceDesk::query()->where('is_active', true)->count(),
            'activeStaffCount' => User::query()->where('role', 'staff')->where('is_active', true)->count(),
            'waitingCount' => $session?->tickets()->where('status', 'waiting')->count() ?? 0,
        ];
    }

    private function sessionData(): array
    {
        return [
            'session' => $this->activeSession(),
            'sessions' => QueueSession::query()->withCount('tickets')->latest('id')->limit(10)->get(),
        ];
    }

    private function activeSession(): ?QueueSession
    {
        $sessionId = DB::table('queue_control')->where('id', 1)->value('active_session_id');

        return $sessionId ? QueueSession::query()->find($sessionId) : null;
    }

    private function deskData(): array
    {
        return [
            'desks' => ServiceDesk::query()
                ->with(['staff' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('id')
                ->get(),
            'activeStatusByDesk' => Ticket::query()
                ->whereIn('status', ['called', 'serving'])
                ->get(['service_desk_id', 'status'])
                ->keyBy('service_desk_id'),
        ];
    }

    private function staffData(): array
    {
        return [
            'desks' => ServiceDesk::query()->orderBy('id')->get(),
            'activeDesks' => ServiceDesk::query()->where('is_active', true)->orderBy('id')->get(),
            'staff' => User::query()->where('role', 'staff')->with('serviceDesk')->orderBy('name')->get(),
        ];
    }

    private function historyData(): array
    {
        return [
            'tickets' => $this->tickets(),
            'ticketEvents' => TicketEvent::query()
                ->with(['ticket.queueSession', 'staff', 'serviceDesk'])
                ->latest('id')
                ->limit(30)
                ->get(),
        ];
    }

    private function tickets(array $statuses = []): LengthAwarePaginator
    {
        return Ticket::query()
            ->with(['staff', 'serviceDesk', 'queueSession'])
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->latest('id')
            ->paginate(40);
    }

    private function auditEntries(): Collection
    {
        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.*', 'users.name as user_name')
            ->latest('audit_logs.id')
            ->limit(40)
            ->get();
    }

    private function assertDeskAvailable(int $deskId, ?int $ignoreStaffId = null): void
    {
        $occupied = User::query()->where('service_desk_id', $deskId)->where('is_active', true)
            ->when($ignoreStaffId, fn ($query) => $query->whereKeyNot($ignoreStaffId))->exists();
        abort_if($occupied, 422, 'This exclusive service desk already has an active staff member.');
    }

    private function audit(Request $request, string $action, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
