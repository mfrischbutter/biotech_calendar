<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Queries\ContractDetailQuery;
use App\Queries\ContractListQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContractController extends Controller
{
    public function index(Request $request, ContractListQuery $list)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('contracts.view'), 403);

        $filters = $request->validate(ContractListQuery::rules());

        $clients = Client::orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'company_name']);

        return Inertia::render('Contracts/Index', [
            'contracts' => $list->paginate($filters),
            'stageCounts' => $list->stageCounts($filters),
            'clients' => $clients,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? ContractListQuery::DEFAULT_SORT,
                'dir' => $filters['dir'] ?? 'asc',
                'view' => $filters['view'] ?? ContractListQuery::VIEW_ALL,
            ],
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
            'access_notes' => ['nullable', 'string', 'max:2000'],
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

    public function show(Request $request, Contract $contract, ContractDetailQuery $detail)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('contracts.view'), 403);

        $contract->load('clients:id,first_name,last_name,company_name');

        $detailed = $detail->forContract($contract, $user->hasPermission('appointments.view'));

        $clients = Client::orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'company_name']);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
            'clients' => $clients,
            'upcomingAppointments' => $detailed['upcomingAppointments'],
            'pastAppointments' => $detailed['pastAppointments'],
            'timeline' => $detailed['timeline'],
            'facts' => $detailed['facts'],
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
            'access_notes' => ['nullable', 'string', 'max:2000'],
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

        // Deleting from the detail page cannot go "back" — that page is gone.
        $validated = $request->validate([
            'redirect' => ['nullable', 'string', 'in:index'],
        ]);

        $contract->delete();

        return ($validated['redirect'] ?? null) === 'index'
            ? redirect()->route('contracts.index')
            : back();
    }
}
