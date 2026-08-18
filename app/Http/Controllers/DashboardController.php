<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Status;
use App\Models\User;
use App\Queries\CalendarQuery;
use App\Queries\ContractListQuery;
use App\Queries\DashboardQuery;
use App\Services\ScheduleConflictService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * The operations view of today.
 *
 * Deliberately built around exceptions ("3 appointments have nobody assigned")
 * rather than totals ("12 clients"), because a total never changes anyone's
 * next action.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardQuery $dashboard,
        private ScheduleConflictService $conflicts,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();

        return Inertia::render('Dashboard/Index', [
            'today' => $now->toDateString(),
            'greetingName' => $user->first_name,
            'attention' => $this->attention($user, $now),
            'schedule' => $this->todaysSchedule($now),
            'pipeline' => $this->dashboard->pipeline(),
            'workload' => $this->dashboard->workload($user->company_id, $now),
            'notifications' => $this->attentionNotifications($user),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    /**
     * The five numbers worth acting on. Each one is counted the way the screen
     * it links to counts: the two job tiles against the contract list's tabs,
     * the conflict tile against the calendar week the link actually opens.
     * A tile whose number does not survive the click is worse than no tile.
     *
     * @return array<string, mixed>
     */
    private function attention(User $user, Carbon $now): array
    {
        $jobs = $this->dashboard->jobCounts();

        $unassigned = Appointment::query()
            ->whereBetween('start_at', [$now->copy()->startOfDay(), $now->copy()->endOfWeek(Carbon::SUNDAY)])
            ->whereDoesntHave('workers')
            ->count();

        // The calendar link lands on its default view, so count that same window.
        [$from, $to] = CalendarQuery::range(CalendarQuery::DEFAULT_VIEW, $now);

        return [
            'unassigned' => $unassigned,
            'overdue' => $jobs[ContractListQuery::VIEW_OVERDUE] ?? 0,
            'readyToInvoice' => $jobs[Status::STAGE_READY_TO_INVOICE] ?? 0,
            'utilisation' => $this->dashboard->averageUtilisation($user->company_id, $now),
            'conflicts' => count($this->conflicts->detectInRange($from, $to)),
        ];
    }

    /**
     * Today's appointments grouped by technician, in time order — the shape a
     * dispatcher actually thinks in.
     *
     * @return array<int, array<string, mixed>>
     */
    private function todaysSchedule(Carbon $now): array
    {
        $appointments = Appointment::query()
            ->with(['contract:id,title,contract_number,street,zip,city', 'workers:id,first_name,last_name', 'status:id,name,color,stage'])
            ->whereBetween('start_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->orderBy('start_at')
            ->get();

        /** @var Collection<string, array<string, mixed>> $groups */
        $groups = collect();

        foreach ($appointments as $appointment) {
            $workers = $appointment->workers->isEmpty()
                ? [null]
                : $appointment->workers->all();

            foreach ($workers as $worker) {
                $key = $worker?->id ?? 'unassigned';

                if (! $groups->has($key)) {
                    $groups->put($key, [
                        'worker_id' => $worker?->id,
                        'worker_name' => $worker?->name,
                        'items' => [],
                    ]);
                }

                $group = $groups->get($key);
                $group['items'][] = [
                    'id' => $appointment->id,
                    'title' => $appointment->contract?->title,
                    'start_at' => $appointment->start_at?->toIso8601String(),
                    'end_at' => $appointment->end_at?->toIso8601String(),
                    'address' => collect([
                        $appointment->contract?->street,
                        trim(($appointment->contract?->zip ?? '').' '.($appointment->contract?->city ?? '')),
                    ])->filter()->implode(', '),
                    'status' => $appointment->status ? [
                        'name' => $appointment->status->name,
                        'color' => $appointment->status->color,
                        'stage' => $appointment->status->stage,
                    ] : null,
                    'state' => $this->liveState($appointment, $now),
                ];
                $groups->put($key, $group);
            }
        }

        // Unassigned first: it is the group that needs a decision.
        return $groups
            ->sortBy(fn (array $g) => $g['worker_id'] === null ? '' : $g['worker_name'])
            ->values()
            ->all();
    }

    /** done | now | upcoming — drives the badge on each row. */
    private function liveState(Appointment $appointment, Carbon $now): string
    {
        if ($appointment->status && in_array($appointment->status->stage, Status::COMPLETED_STAGES, true)) {
            return 'done';
        }

        if ($appointment->start_at?->lte($now) && $appointment->end_at?->gte($now)) {
            return 'now';
        }

        return $appointment->end_at?->lt($now) ? 'past' : 'upcoming';
    }

    /** @return array<int, array<string, mixed>> */
    private function attentionNotifications(User $user): array
    {
        return Notification::forUser($user->id)
            ->unread()
            ->with(['actor:id,first_name,last_name', 'appointment:id,contract_id', 'appointment.contract:id,title'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'severity' => $n->severity(),
                'actor' => $n->actor?->name,
                'title' => $n->appointment?->contract?->title ?? ($n->data['title'] ?? null),
                'excerpt' => $n->data['excerpt'] ?? null,
                'url' => $n->appointment_id ? route('calendar.index', ['appointment' => $n->appointment_id]) : null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function recentActivity(): array
    {
        return ActivityLog::with([
            'user:id,first_name,last_name',
            'appointment:id,contract_id',
            'appointment.contract:id,title',
        ])
            ->recent(7)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user ? trim($log->user->first_name.' '.$log->user->last_name) : null,
                'title' => $log->appointment?->contract?->title,
                'url' => $log->appointment_id ? route('calendar.index', ['appointment' => $log->appointment_id]) : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function markCommentsRead(Request $request)
    {
        $request->user()->update([
            'dashboard_comments_read_at' => now(),
        ]);

        return back();
    }
}
