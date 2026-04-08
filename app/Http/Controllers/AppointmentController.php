<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
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
        ]);

        $user = $request->user();
        abort_unless($user->hasPermission('appointments.view'), 403);

        $view = $request->input('view', 'week');
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::now();

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

        $appointments = Appointment::with(['client:id,first_name,last_name,company_name,street,zip,city', 'employee:id,first_name,last_name', 'status:id,name,color'])
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

        $clients = Client::orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name', 'street', 'zip', 'city']);
        $employees = User::where('company_id', $user->company_id)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role']);

        $statuses = Status::orderBy('sort_order')->get(['id', 'name', 'color']);

        return Inertia::render('Calendar/Index', [
            'appointments' => $appointments,
            'clients' => $clients,
            'employees' => $employees,
            'statuses' => $statuses,
            'currentDate' => $date->toDateString(),
            'view' => $view,
            'showWeekends' => $showWeekends,
            'startHour' => $startHour,
            'endHour' => $endHour,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('appointments.create'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', Appointment::KINDS)],
            'notes' => ['nullable', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['required', 'string', 'max:500'],
            'checklist.*.checked' => ['required', 'boolean'],
            'recurrence_type' => ['nullable', 'string', 'in:'.implode(',', Appointment::RECURRENCE_TYPES)],
            'recurrence_interval' => [
                Rule::when($request->input('recurrence_type') === 'custom', ['required', 'integer', 'min:1'], ['nullable', 'integer', 'min:1']),
            ],
            'recurrence_end' => ['nullable', 'date', 'after:start_at'],
        ]);

        $baseData = [
            'title' => $validated['title'],
            'client_id' => $validated['client_id'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'status_id' => $validated['status_id'] ?? null,
            'kind' => $validated['kind'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'street' => $validated['street'] ?? null,
            'zip' => $validated['zip'] ?? null,
            'city' => $validated['city'] ?? null,
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

            $this->generateOccurrences($parent, $baseData, $validated);
        } else {
            Appointment::create($baseData);
        }

        return back();
    }

    public function update(Request $request, Appointment $appointment)
    {
        abort_unless($request->user()->hasPermission('appointments.edit'), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date', 'after:start_at'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', Appointment::KINDS)],
            'notes' => ['nullable', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['required', 'string', 'max:500'],
            'checklist.*.checked' => ['required', 'boolean'],
        ]);

        $appointment->update($validated);

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

    private function generateOccurrences(Appointment $parent, array $baseData, array $validated): void
    {
        $startAt = Carbon::parse($validated['start_at']);
        $endAt = Carbon::parse($validated['end_at']);
        $duration = $startAt->diffInMinutes($endAt);
        $recurrenceEnd = Carbon::parse($validated['recurrence_end']);

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

            Appointment::create([
                ...$baseData,
                'start_at' => $current->copy(),
                'end_at' => $current->copy()->addMinutes($duration),
                'parent_id' => $parent->id,
            ]);

            $count++;
        }
    }
}
