<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\QueueSession;
use App\Models\QueueSetting;
use App\Models\ServiceDesk;
use App\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicQueueController extends Controller
{
    public function home(): View
    {
        $settings = QueueSetting::query()->firstOrFail();
        $active = $this->activeSession();
        $latestAnnouncement = $active
            ? Announcement::query()->with(['ticket', 'serviceDesk'])->whereHas('ticket', fn ($query) => $query->where('queue_session_id', $active->id))->latest('id')->first()
            : null;

        return view('welcome', compact('settings', 'active', 'latestAnnouncement'));
    }

    public function issue(Request $request, QueueService $queue): View|RedirectResponse
    {
        $validated = $request->validate(['request_key' => ['required', 'uuid']]);
        try {
            $ticket = $queue->issueTicket($validated['request_key']);
        } catch (ValidationException $exception) {
            return redirect()->route('home')->withErrors($exception->errors())->withInput();
        }

        return view('ticket', [
            'ticket' => $ticket->load('queueSession'),
            'organization' => QueueSetting::query()->value('organization_name'),
        ]);
    }

    public function display(): View
    {
        return view('display');
    }

    public function displayState(Request $request): JsonResponse
    {
        $cursor = max(0, (int) $request->query('cursor', 0));
        $session = $this->activeSession() ?? QueueSession::query()->latest('id')->first();
        $settings = QueueSetting::query()->firstOrFail();

        if (! $session) {
            return response()->json([
                'organization' => $settings->organization_name,
                'logo_url' => $settings->logo_url,
                'status' => 'closed',
                'session' => null,
                'waiting_count' => 0,
                'current' => null,
                'recent' => [],
                'announcements' => [],
                'cursor' => $cursor,
                'language' => $settings->announcement_language,
                'voice' => $settings->announcement_voice,
            ]);
        }

        $calls = Announcement::query()
            ->with(['ticket:id,ticket_number,status,queue_session_id', 'serviceDesk:id,name,code'])
            ->whereHas('ticket', fn ($query) => $query->where('queue_session_id', $session->id))
            ->latest('id')
            ->limit(8)
            ->get();
        $currentCall = Announcement::query()
            ->with(['ticket:id,ticket_number,status,queue_session_id', 'serviceDesk:id,name,code'])
            ->whereHas('ticket', fn ($query) => $query->where('queue_session_id', $session->id)->whereIn('status', ['called', 'serving']))
            ->latest('id')
            ->first();
        $hasCursor = $request->query->has('cursor');
        $newCalls = $hasCursor && $session->status === 'open'
            ? Announcement::query()
                ->with(['ticket:id,ticket_number,status,queue_session_id', 'serviceDesk:id,name,code'])
                ->where('id', '>', $cursor)
                ->whereHas('ticket', fn ($query) => $query->where('queue_session_id', $session->id))
                ->orderBy('id')
                ->limit(20)
                ->get()
            : collect();

        return response()->json([
            'organization' => $settings->organization_name,
            'logo_url' => $settings->logo_url,
            'status' => $session->status,
            'session' => $session->label,
            'waiting_count' => $session->tickets()->where('status', 'waiting')->count(),
            'current' => $currentCall ? $this->announcementPayload($currentCall) : null,
            'recent' => $calls->map(fn (Announcement $announcement) => $this->announcementPayload($announcement))->values(),
            'announcements' => $newCalls->map(fn (Announcement $announcement) => $this->announcementPayload($announcement))->values(),
            'cursor' => $hasCursor
                ? max($cursor, (int) ($newCalls->last()?->id ?? $cursor))
                : (int) Announcement::query()
                    ->whereHas('ticket', fn ($query) => $query->where('queue_session_id', $session->id))
                    ->max('id'),
            'language' => $settings->announcement_language,
            'voice' => $settings->announcement_voice,
        ]);
    }

    public function ticket(Ticket $ticket): View
    {
        return view('ticket', [
            'ticket' => $ticket->load('queueSession'),
            'organization' => QueueSetting::query()->value('organization_name'),
        ]);
    }

    private function activeSession(): ?QueueSession
    {
        $id = DB::table('queue_control')->where('id', 1)->value('active_session_id');

        return $id ? QueueSession::query()->find($id) : null;
    }

    private function announcementPayload(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'uuid' => $announcement->event_uuid,
            'ticket' => $announcement->ticket->ticket_number,
            'desk' => $announcement->serviceDesk->name,
            'desk_code' => $announcement->serviceDesk->code,
        ];
    }
}
