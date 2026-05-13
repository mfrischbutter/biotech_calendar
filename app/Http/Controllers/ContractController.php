<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('contracts.view'), 403);

        $search = $request->input('search');

        $contracts = Contract::with('clients:id,first_name,last_name,company_name')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('contract_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('clients', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('company_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $clients = Client::orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'company_name']);

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('contracts.create'), 403);

        $validated = $request->validate([
            'contract_number' => [
                'required', 'string', 'max:50',
                Rule::unique('contracts')->where('company_id', $user->company_id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', Contract::KINDS)],
            'description' => ['nullable', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
        ]);

        $contract = Contract::create(collect($validated)->except('client_ids')->toArray());

        if (! empty($validated['client_ids'])) {
            $contract->clients()->sync($validated['client_ids']);
        }

        return redirect()->route('contracts.show', $contract->id);
    }

    public function show(Request $request, Contract $contract)
    {
        abort_unless($request->user()->hasPermission('contracts.view'), 403);

        $contract->load('clients:id,first_name,last_name,company_name');

        $now = Carbon::now();

        $upcomingAppointments = Appointment::where('contract_id', $contract->id)
            ->where('start_at', '>=', $now)
            ->with(['workers:id,first_name,last_name', 'status:id,name,color'])
            ->orderBy('start_at')
            ->limit(20)
            ->get();

        $pastAppointments = Appointment::where('contract_id', $contract->id)
            ->where('start_at', '<', $now)
            ->with(['workers:id,first_name,last_name', 'status:id,name,color'])
            ->orderByDesc('start_at')
            ->limit(20)
            ->get();

        $totalAppointments = Appointment::where('contract_id', $contract->id)->count();
        $upcomingCount = Appointment::where('contract_id', $contract->id)
            ->where('start_at', '>=', $now)
            ->count();
        $lastAppointment = Appointment::where('contract_id', $contract->id)
            ->where('start_at', '<', $now)
            ->orderByDesc('start_at')
            ->value('start_at');
        $nextAppointment = Appointment::where('contract_id', $contract->id)
            ->where('start_at', '>=', $now)
            ->orderBy('start_at')
            ->value('start_at');

        $clients = Client::orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'company_name']);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
            'clients' => $clients,
            'upcomingAppointments' => $upcomingAppointments,
            'pastAppointments' => $pastAppointments,
            'stats' => [
                'totalAppointments' => $totalAppointments,
                'upcomingAppointments' => $upcomingCount,
                'lastAppointment' => $lastAppointment?->toISOString(),
                'nextAppointment' => $nextAppointment?->toISOString(),
            ],
        ]);
    }

    public function update(Request $request, Contract $contract)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('contracts.edit'), 403);

        $validated = $request->validate([
            'contract_number' => [
                'required', 'string', 'max:50',
                Rule::unique('contracts')->where('company_id', $user->company_id)->ignore($contract->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', Contract::KINDS)],
            'description' => ['nullable', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
        ]);

        $contract->update(collect($validated)->except('client_ids')->toArray());
        $contract->clients()->sync($validated['client_ids'] ?? []);

        return back();
    }

    public function destroy(Request $request, Contract $contract)
    {
        abort_unless($request->user()->hasPermission('contracts.delete'), 403);

        $contract->delete();

        return back();
    }
}
