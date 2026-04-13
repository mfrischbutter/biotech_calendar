<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Comment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('clients.view'), 403);

        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Client::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('last_name')->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Request $request, Client $client)
    {
        abort_unless($request->user()->hasPermission('clients.view'), 403);

        $client->load('contracts');

        $contractIds = $client->contracts->pluck('id');

        $now = Carbon::now();

        $upcomingAppointments = $contractIds->isEmpty() ? collect() : Appointment::whereIn('contract_id', $contractIds)
            ->where('start_at', '>=', $now)
            ->with(['contract:id,contract_number,title,kind', 'workers:id,first_name,last_name', 'status:id,name,color'])
            ->orderBy('start_at')
            ->limit(20)
            ->get();

        $pastAppointments = $contractIds->isEmpty() ? collect() : Appointment::whereIn('contract_id', $contractIds)
            ->where('start_at', '<', $now)
            ->with(['contract:id,contract_number,title,kind', 'workers:id,first_name,last_name', 'status:id,name,color'])
            ->orderByDesc('start_at')
            ->limit(20)
            ->get();

        $appointmentIds = $contractIds->isEmpty() ? collect() : Appointment::whereIn('contract_id', $contractIds)->pluck('id');

        $recentActivity = $appointmentIds->isEmpty() ? collect() : ActivityLog::whereIn('appointment_id', $appointmentIds)
            ->where('action', '!=', 'comment_added')
            ->with(['user:id,first_name,last_name', 'appointment:id,contract_id', 'appointment.contract:id,title'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $recentComments = $appointmentIds->isEmpty() ? collect() : Comment::whereIn('appointment_id', $appointmentIds)
            ->with(['user:id,first_name,last_name', 'appointment:id,contract_id', 'appointment.contract:id,title'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $totalAppointments = $appointmentIds->count();
        $upcomingCount = $contractIds->isEmpty() ? 0 : Appointment::whereIn('contract_id', $contractIds)
            ->where('start_at', '>=', $now)
            ->count();

        $lastAppointment = $contractIds->isEmpty() ? null : Appointment::whereIn('contract_id', $contractIds)
            ->where('start_at', '<', $now)
            ->orderByDesc('start_at')
            ->value('start_at');

        $nextAppointment = $contractIds->isEmpty() ? null : Appointment::whereIn('contract_id', $contractIds)
            ->where('start_at', '>=', $now)
            ->orderBy('start_at')
            ->value('start_at');

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'contracts' => $client->contracts,
            'upcomingAppointments' => $upcomingAppointments,
            'pastAppointments' => $pastAppointments,
            'recentActivity' => $recentActivity,
            'recentComments' => $recentComments,
            'stats' => [
                'totalContracts' => $client->contracts->count(),
                'totalAppointments' => $totalAppointments,
                'upcomingAppointments' => $upcomingCount,
                'lastAppointment' => $lastAppointment?->toISOString(),
                'nextAppointment' => $nextAppointment?->toISOString(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('clients.create'), 403);

        $validated = $request->validate([
            'salutation' => ['nullable', 'string', Rule::in(Client::SALUTATIONS)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
        ]);

        Client::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function update(Request $request, Client $client)
    {
        abort_unless($request->user()->hasPermission('clients.edit'), 403);

        $validated = $request->validate([
            'salutation' => ['nullable', 'string', Rule::in(Client::SALUTATIONS)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
        ]);

        $client->update($validated);

        return back();
    }

    public function destroy(Request $request, Client $client)
    {
        abort_unless($request->user()->hasPermission('clients.delete'), 403);

        $client->delete();

        return back();
    }
}
