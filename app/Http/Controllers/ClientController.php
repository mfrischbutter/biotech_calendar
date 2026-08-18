<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Queries\ClientDetailQuery;
use App\Queries\ClientListQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request, ClientListQuery $list)
    {
        abort_unless($request->user()->hasPermission('clients.view'), 403);

        $filters = $request->validate(ClientListQuery::rules());

        return Inertia::render('Clients/Index', [
            'clients' => $list->paginate($filters),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? ClientListQuery::DEFAULT_SORT,
                'dir' => $filters['dir'] ?? 'asc',
                'view' => $filters['view'] ?? ClientListQuery::VIEW_ALL,
            ],
        ]);
    }

    public function show(Request $request, Client $client, ClientDetailQuery $detail)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('clients.view'), 403);

        $client->load('contracts');

        $detailed = $detail->forClient($client, $user->hasPermission('appointments.view'));

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'contracts' => $client->contracts,
            'upcomingAppointments' => $detailed['upcomingAppointments'],
            'pastAppointments' => $detailed['pastAppointments'],
            'timeline' => $detailed['timeline'],
            'facts' => $detailed['facts'],
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
            'access_notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
        ]);

        $client = Client::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('clients.show', $client->id);
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
            'access_notes' => ['nullable', 'string', 'max:2000'],
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

        // Deleting from the detail page cannot go "back" — that page is gone.
        $validated = $request->validate([
            'redirect' => ['nullable', 'string', 'in:index'],
        ]);

        $client->delete();

        return ($validated['redirect'] ?? null) === 'index'
            ? redirect()->route('clients.index')
            : back();
    }
}
