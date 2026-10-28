<?php

namespace App\Http\Controllers;

use App\Models\QueueSession;
use App\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user()->load('serviceDesk');
        $activeSessionId = DB::table('queue_control')->where('id', 1)->value('active_session_id');
        $session = $activeSessionId ? QueueSession::query()->find($activeSessionId) : QueueSession::query()->latest('id')->first();
        $current = Ticket::query()->with('serviceDesk')->where('assigned_staff_id', $staff->id)->whereIn('status', ['called', 'serving'])->latest('id')->first();
        $waitingCount = $session?->tickets()->where('status', 'waiting')->count() ?? 0;
        $servedCount = $session ? Ticket::query()->where('queue_session_id', $session->id)->where('assigned_staff_id', $staff->id)->where('status', 'completed')->count() : 0;

        return view('staff', compact('staff', 'session', 'current', 'waitingCount', 'servedCount'));
    }

    public function state(Request $request): JsonResponse
    {
        $staff = $request->user();
        $sessionId = DB::table('queue_control')->where('id', 1)->value('active_session_id');
        $session = $sessionId ? QueueSession::query()->find($sessionId) : null;
        $ticket = Ticket::query()->with('serviceDesk')->where('assigned_staff_id', $staff->id)->whereIn('status', ['called', 'serving'])->latest('id')->first();

        return response()->json([
            'session_status' => $session?->status ?? 'closed',
            'waiting_count' => $session?->tickets()->where('status', 'waiting')->count() ?? 0,
            'ticket' => $ticket ? ['number' => $ticket->ticket_number, 'status' => $ticket->status, 'desk' => $ticket->serviceDesk?->name] : null,
        ]);
    }

    public function claim(Request $request, QueueService $queue): RedirectResponse
    {
        $ticket = $queue->claimNext($request->user());
        return redirect()->route('staff.index')->with('status', $ticket ? "Now calling {$ticket->ticket_number}." : 'There are no waiting customers right now.');
    }

    public function accept(Request $request, QueueService $queue): RedirectResponse
    {
        $ticket = $queue->accept($request->user());
        return redirect()->route('staff.index')->with('status', "{$ticket->ticket_number} is now being served.");
    }

    public function skip(Request $request, QueueService $queue): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $ticket = $queue->skip($request->user(), $validated['reason'] ?? null);
        return redirect()->route('staff.index')->with('status', "{$ticket->ticket_number} was skipped and handled according to the queue policy.");
    }

    public function complete(Request $request, QueueService $queue): RedirectResponse
    {
        $ticket = $queue->complete($request->user());
        return redirect()->route('staff.index')->with('status', "{$ticket->ticket_number} is complete.");
    }
}
