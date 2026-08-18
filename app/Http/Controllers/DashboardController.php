<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Status;
use App\Models\User;
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
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $now = Carbon::now();

        return Inertia::render('Dashboard/Index', [
            'today' => $now->toDateString(),
            'greetingName' => $user->first_name,
            'attention' => $this->attention($user, $now),
            'schedule' => $this->todaysSchedule($now),
            'pipeline' => $this->pipeline(),
            'workload' => $this->workload($now),
            'notifications' => $this->attentionNotifications($user),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    /**
     * The four numbers worth acting on, each with a link to the filtered list.
     *
     * @return array<string, mixed>
     */
    private function attention(User $user, Carbon $now): array
    {
        $todayStart = $now->copy()->startOfDay();
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $unassigned = Appointment::query()
            ->whereBetween('start_at', [$todayStart, $weekEnd])
            ->whereDoesntHave('workers')
            ->count();

        // Work that has already happened but never reached a finished status.
        $overdue = Appointment::query()
            ->where('end_at', '<', $now)
            ->where('start_at', '>', $now->copy()->subMonths(3))
            ->where(fn ($q) => $q
                ->whereNull('status_id')
                ->orWhereHas('status', fn ($s) => $s->whereNotIn('stage', [
                    Status::STAGE_READY_TO_INVOICE,
                    Status::STAGE_INVOICED,
                    Status::STAGE_CANCELLED,
                ])))
            ->count();

        $readyToInvoice = Appointment::query()
            ->whereHas('status', fn ($s) => $s->where('stage', Status::STAGE_READY_TO_INVOICE))
            ->count();

        return [
            'unassigned' => $unassigned,
            'overdue' => $overdue,
            'readyToInvoice' => $readyToInvoice,
            'utilisation' => $this->averageUtilisation($now),
            'conflicts' => Notification::forUser($user->id)
                ->where('type', Notification::TYPE_CONFLICT)
                ->unread()
                ->count(),
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

    /**
     * Counts per pipeline stage — the same data the board view uses.
     *
     * @return array<int, array{stage: string, count: int}>
     */
    private function pipeline(): array
    {
        $counts = Appointment::query()
            ->join('statuses', 'statuses.id', '=', 'appointments.status_id')
            ->groupBy('statuses.stage')
            ->selectRaw('statuses.stage as stage, count(*) as total')
            ->pluck('total', 'stage');

        return collect(Status::PIPELINE_STAGES)
            ->map(fn (string $stage) => [
                'stage' => $stage,
                'count' => (int) ($counts[$stage] ?? 0),
            ])
            ->all();
    }

    /**
     * Utilisation per technician for the current week, against a 40h week.
     *
     * @return array<int, array{id: int, name: string, appointments: int, percent: int}>
     */
    private function workload(Carbon $now): array
    {
        $start = $now->copy()->startOfWeek(Carbon::MONDAY);
        $end = $now->copy()->endOfWeek(Carbon::SUNDAY);
        $capacity = 40 * 60;

        $rows = Appointment::query()
            ->whereBetween('start_at', [$start, $end])
            ->join('appointment_user', 'appointment_user.appointment_id', '=', 'appointments.id')
            ->groupBy('appointment_user.user_id')
            ->selectRaw('appointment_user.user_id as uid, count(*) as total, sum(timestampdiff(minute, start_at, end_at)) as minutes')
            ->get()
            ->keyBy('uid');

        return User::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'appointments' => (int) ($rows[$u->id]->total ?? 0),
                'percent' => (int) min(100, round((((int) ($rows[$u->id]->minutes ?? 0)) / $capacity) * 100)),
            ])
            ->filter(fn (array $row) => $row['appointments'] > 0)
            ->values()
            ->all();
    }

    private function averageUtilisation(Carbon $now): int
    {
        $rows = $this->workload($now);

        if ($rows === []) {
            return 0;
        }

        return (int) round(collect($rows)->avg('percent'));
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
