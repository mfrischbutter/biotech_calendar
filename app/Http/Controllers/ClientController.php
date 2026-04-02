<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
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
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('name')->get();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('clients.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
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
            'name' => ['required', 'string', 'max:255'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
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
