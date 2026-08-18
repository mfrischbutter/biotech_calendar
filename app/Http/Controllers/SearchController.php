<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Powers the search field in the top bar.
 *
 * Deliberately a visible search box rather than a keyboard-only command
 * palette: the people using this app should not have to know a shortcut to
 * find a client or start a new appointment. Actions are returned alongside
 * records so "Neuen Termin anlegen" is reachable by typing, not just by
 * navigating to the right page first.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $q = trim($validated['q'] ?? '');

        // With no query we still return the actions, so opening the field is useful.
        $clients = $q === '' ? collect() : $this->clients($user, $q);
        $contracts = $q === '' ? collect() : $this->contracts($user, $q);
        $appointments = $q === '' ? collect() : $this->appointments($user, $q);

        return response()->json([
            'query' => $q,
            'groups' => array_values(array_filter([
                $this->group('clients', __('Clients'), $clients),
                $this->group('contracts', __('Contracts'), $contracts),
                $this->group('appointments', __('Appointments'), $appointments),
                $this->group('actions', __('Actions'), $this->actions($user, $q, $clients)),
            ], fn (?array $group) => $group !== null)),
        ]);
    }

    /** @return array{key: string, label: string, items: array<int, mixed>}|null */
    private function group(string $key, string $label, mixed $items): ?array
    {
        $items = collect($items)->values()->all();

        return $items === [] ? null : ['key' => $key, 'label' => $label, 'items' => $items];
    }

    private function clients($user, string $q)
    {
        if (! $user->hasPermission('clients.view')) {
            return collect();
        }

        return Client::query()
            ->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('company_name', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"))
            ->orderBy('last_name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'type' => 'client',
                'title' => $c->name,
                'subtitle' => collect([$c->company_name, $c->city])->filter()->implode(' · '),
                'url' => route('clients.show', $c->id),
            ]);
    }

    private function contracts($user, string $q)
    {
        if (! $user->hasPermission('contracts.view')) {
            return collect();
        }

        return Contract::query()
            ->where(fn ($query) => $query
                ->where('title', 'like', "%{$q}%")
                ->orWhere('contract_number', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'type' => 'contract',
                'title' => $c->title,
                'subtitle' => $c->contract_number,
                'url' => route('contracts.show', $c->id),
            ]);
    }

    private function appointments($user, string $q)
    {
        if (! $user->hasPermission('appointments.view')) {
            return collect();
        }

        return Appointment::query()
            ->with(['contract:id,title,contract_number'])
            ->whereHas('contract', fn ($c) => $c
                ->where('title', 'like', "%{$q}%")
                ->orWhere('contract_number', 'like', "%{$q}%"))
            ->orderByDesc('start_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'type' => 'appointment',
                'title' => $a->contract?->title ?? __('Appointment'),
                'subtitle' => $a->start_at?->translatedFormat('D, j. M Y · H:i'),
                'url' => route('calendar.index', ['appointment' => $a->id]),
            ]);
    }

    /**
     * Actions the user can take right now. Client-specific shortcuts appear
     * when the query already matched a client, so "Termin für Bergmann"
     * is one keystroke path instead of three screens.
     */
    private function actions($user, string $q, $clients)
    {
        $actions = collect();

        if ($user->hasPermission('appointments.create')) {
            $actions->push([
                'id' => 'new-appointment',
                'type' => 'action',
                'title' => __('New Appointment'),
                'subtitle' => __('Open the calendar and create an appointment'),
                'url' => route('calendar.index', ['new' => 1]),
                'icon' => 'calendar-plus',
            ]);
        }

        if ($user->hasPermission('clients.create')) {
            $actions->push([
                'id' => 'new-client',
                'type' => 'action',
                'title' => __('New Client'),
                'subtitle' => __('Add a client to the database'),
                'url' => route('clients.index', ['new' => 1]),
                'icon' => 'user-plus',
            ]);
        }

        if ($user->hasPermission('contracts.create')) {
            $actions->push([
                'id' => 'new-contract',
                'type' => 'action',
                'title' => __('New Contract'),
                'subtitle' => __('Create a contract for a client'),
                'url' => route('contracts.index', ['new' => 1]),
                'icon' => 'file-plus',
            ]);
        }

        foreach (collect($clients)->take(2) as $client) {
            if (! $user->hasPermission('appointments.create')) {
                break;
            }

            $actions->push([
                'id' => 'new-appointment-client-'.$client['id'],
                'type' => 'action',
                'title' => __('New appointment for :name', ['name' => $client['title']]),
                'subtitle' => __('Opens the calendar with this client preselected'),
                'url' => route('calendar.index', ['new' => 1, 'client' => $client['id']]),
                'icon' => 'calendar-plus',
            ]);
        }

        if ($q === '') {
            return $actions;
        }

        // Once the user is typing, only show actions whose label is relevant.
        $needle = mb_strtolower($q);

        return $actions->filter(
            fn (array $a) => str_contains(mb_strtolower($a['title']), $needle)
                || str_starts_with($a['id'], 'new-appointment-client-')
        );
    }
}
