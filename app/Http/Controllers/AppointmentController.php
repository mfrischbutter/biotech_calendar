<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    private const MAX_OCCURRENCES = 500;

    public function index(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'view' => ['nullable', 'in:day,week,month,team-day,team-week'],
            'appointment' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        abort_unless($user->hasPermission('appointments.view'), 403);

        $openAppointmentId = $request->integer('appointment') ?: null;
        $openAppointment = $openAppointmentId ? Appointment::find($openAppointmentId) : null;

        $view = $request->input('view', 'week');
        $date = $openAppointment
            ? $openAppointment->start_at->copy()
            : ($request->input('date')
                ? Carbon::parse($request->input('date'))
                : Carbon::now());

        $showWeekends = Setting::get('show_weekends', 'false') === 'true';
        $startHour = (int) Setting::get('start_hour', '0');
        $endHour = (int) Setting::get('end_hour', '24');

        [$rangeStart, $rangeEnd] = match ($view) {
            'day', 'team-day' => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
            'month' => [
                $date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $date->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            ],
            default => [
                $date->copy()->startOfWeek(Carbon::MONDAY),
                $date->copy()->endOfWeek(Carbon::SUNDAY),
            ],
        };

        $appointments = Appointment::with([
            'contract:id,contract_number,title,kind,street,zip,city',
            'contract.clients:id,first_name,last_name,company_name',
            'workers:id,first_name,last_name',
            'status:id,name,color',
            'comments.user:id,first_name,last_name',
            'comments.attachments',
            'attachments.user:id,first_name,last_name',
        ])
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('start_at', [$rangeStart, $rangeEnd])
                    ->orWhereBetween('end_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($q) use ($rangeStart, $rangeEnd) {
                        $q->where('start_at', '<', $rangeStart)
                            ->where('end_at', '>', $rangeEnd);
                    });
            })
            ->orderBy('start_at')
            ->get();

        $contracts = Contract::with('clients:id,first_name,last_name,company_name')
            ->orderBy('title')
            ->get(['id', 'contract_number', 'title', 'kind', 'street', 'zip', 'city']);

        $employees = User::where('company_id', $user->company_id)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role']);

        $statuses = Status::orderBy('sort_order')->get(['id', 'name', 'color']);

        $clients = Client::orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'company_name']);

        return Inertia::render('Calendar/Index', [
            'appointments' => $appointments,
            'contracts' => $contracts,
            'employees' => $employees,
            'statuses' => $statuses,
            'clients' => $clients,
            'currentDate' => $date->toDateString(),
            'view' => $view,
            'showWeekends' => $showWeekends,
            'startHour' => $startHour,
            'endHour' => $endHour,
            'openAppointmentId' => $openAppointmentId,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('appointments.create'), 403);

        $validated = $request->validate([
            'contract_id' => ['required', 'exists:contracts,id'],
            'worker_ids' => ['nullable', 'array'],
            'worker_ids.*' => ['integer', 'exists:users,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'notes' => ['nullable', 'string'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['required', 'string', 'max:500'],
            'checklist.*.checked' => ['required', 'boolean'],
            'recurrence_type' => ['nullable', 'string', 'in:'.implode(',', Appointment::RECURRENCE_TYPES)],
            'recurrence_interval' => [
                Rule::when($request->input('recurrence_type') === 'custom', ['required', 'integer', 'min:1'], ['nullable', 'integer', 'min:1']),
            ],
            'recurrence_end' => ['nullable', 'date', 'after:start_at'],
        ]);

        $workerIds = $validated['worker_ids'] ?? [];

        $baseData = [
            'contract_id' => $validated['contract_id'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'status_id' => $validated['status_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'checklist' => $validated['checklist'] ?? null,
            'created_by' => $request->user()->id,
        ];

        if (! empty($validated['recurrence_type']) && ! empty($validated['recurrence_end'])) {
            $parent = Appointment::create([
                ...$baseData,
                'recurrence_type' => $validated['recurrence_type'],
                'recurrence_interval' => $validated['recurrence_interval'] ?? null,
                'recurrence_end' => $validated['recurrence_end'],
            ]);
            $parent->workers()->sync($workerIds);

            $this->generateOccurrences($parent, $baseData, $validated, $workerIds);
        } else {
            $appointment = Appointment::create($baseData);
            $appointment->workers()->sync($workerIds);
        }

        return back();
    }

    public function update(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->hasPermission('appointments.edit'), 403);

        $validated = $request->validate([
            'contract_id' => ['sometimes', 'required', 'exists:contracts,id'],
            'worker_ids' => ['nullable', 'array'],
            'worker_ids.*' => ['integer', 'exists:users,id'],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date', 'after:start_at'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'notes' => ['nullable', 'string'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['required', 'string', 'max:500'],
            'checklist.*.checked' => ['required', 'boolean'],
        ]);

        $workerIds = $validated['worker_ids'] ?? null;
        $updateData = collect($validated)->except('worker_ids')->toArray();

        $appointment->update($updateData);

        if ($workerIds !== null) {
            $appointment->workers()->sync($workerIds);
        }

        return back();
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->hasPermission('appointments.delete'), 403);

        $request->validate([
            'delete_series' => ['boolean'],
            'delete_future' => ['boolean'],
        ]);

        if ($request->boolean('delete_series')) {
            if ($appointment->isParent()) {
                $appointment->occurrences()->delete();
                $appointment->delete();
            } elseif ($appointment->parent_id) {
                $parent = $appointment->parent;
                if ($parent) {
                    $parent->occurrences()->delete();
                    $parent->delete();
                }
            } else {
                $appointment->delete();
            }
        } elseif ($request->boolean('delete_future')) {
            $parentId = $appointment->parent_id ?? $appointment->id;
            Appointment::where('parent_id', $parentId)
                ->where('start_at', '>=', $appointment->start_at)
                ->delete();
            if ($appointment->isParent()) {
                $appointment->delete();
            }
        } else {
            $appointment->delete();
        }

        return back();
    }

    private function generateOccurrences(Appointment $parent, array $baseData, array $validated, array $workerIds): void
    {
        $startAt = Carbon::parse($validated['start_at']);
        $endAt = Carbon::parse($validated['end_at']);
        $duration = (int) abs($startAt->diffInMinutes($endAt));
        $recurrenceEnd = Carbon::parse($validated['recurrence_end'])->endOfDay();

        $current = $startAt->copy();
        $count = 0;

        while ($count < self::MAX_OCCURRENCES) {
            if ($validated['recurrence_type'] === 'monthly') {
                $current->addMonth();
            } else {
                $weeks = match ($validated['recurrence_type']) {
                    'weekly' => 1,
                    'biweekly' => 2,
                    'custom' => $validated['recurrence_interval'] ?? 1,
                    default => 1,
                };
                $current->addWeeks($weeks);
            }

            if ($current->gt($recurrenceEnd)) {
                break;
            }

            $occurrence = Appointment::create([
                ...$baseData,
                'start_at' => $current->copy(),
                'end_at' => $current->copy()->addMinutes($duration),
                'parent_id' => $parent->id,
            ]);
            $occurrence->workers()->sync($workerIds);

            $count++;
        }
    }
}
