<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view', 'week');
        $date = $request->input('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::now();

        $showWeekends = Setting::get('show_weekends', 'false') === 'true';

        [$rangeStart, $rangeEnd] = match ($view) {
            'day' => [
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

        $appointments = Appointment::with(['client:id,name', 'employee:id,name'])
            ->where('start_at', '>=', $rangeStart)
            ->where('start_at', '<=', $rangeEnd)
            ->orderBy('start_at')
            ->get();

        $clients = Client::orderBy('name')->get(['id', 'name']);
        $employees = User::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Calendar/Index', [
            'appointments' => $appointments,
            'clients' => $clients,
            'employees' => $employees,
            'currentDate' => $date->toDateString(),
            'view' => $view,
            'showWeekends' => $showWeekends,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(Appointment::TYPES))],
            'notes' => ['nullable', 'string'],
            'recurrence_type' => ['nullable', 'string', 'in:' . implode(',', Appointment::RECURRENCE_TYPES)],
            'recurrence_interval' => ['nullable', 'integer', 'min:1'],
            'recurrence_end' => ['nullable', 'date', 'after:start_at'],
        ]);

        $baseData = [
            'title' => $validated['title'],
            'client_id' => $validated['client_id'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'type' => $validated['type'],
            'notes' => $validated['notes'] ?? null,
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
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'required', 'date', 'after:start_at'],
            'type' => ['sometimes', 'required', 'string', 'in:' . implode(',', array_keys(Appointment::TYPES))],
            'notes' => ['nullable', 'string'],
        ]);

        $appointment->update($validated);

        return back();
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        if ($request->boolean('delete_series') && $appointment->isParent()) {
            $appointment->occurrences()->delete();
            $appointment->delete();
        } elseif ($request->boolean('delete_series') && $appointment->parent_id) {
            $parent = $appointment->parent;
            if ($parent) {
                $parent->occurrences()->delete();
                $parent->delete();
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

        while (true) {
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
        }
    }
}
