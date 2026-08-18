<?php

namespace App\Queries;

use App\Models\Appointment;
use Carbon\Carbon;

/**
 * The derived facts a detail page pins next to the record: the next visit, the
 * series it belongs to, and a routable address.
 *
 * Shared by the client and the contract page — both reduce to "these contract
 * ids" — so the two screens can never drift apart in what they mean by
 * "Nächster Termin".
 */
class RecordFacts
{
    /**
     * The next booked visit, with everything a dispatcher needs to act on it.
     *
     * @param  array<int, int>  $contractIds
     * @return array<string, mixed>|null
     */
    public function nextAppointment(array $contractIds, Carbon $now): ?array
    {
        if ($contractIds === []) {
            return null;
        }

        $appointment = Appointment::query()
            ->whereIn('contract_id', $contractIds)
            ->where('start_at', '>=', $now)
            ->with(['contract:id,contract_number,title', 'status:id,name,color,stage', 'workers:id,first_name,last_name'])
            ->orderBy('start_at')
            ->first();

        if (! $appointment) {
            return null;
        }

        return [
            'id' => $appointment->id,
            'title' => $appointment->contract?->title ?? __('Appointment'),
            'contract_number' => $appointment->contract?->contract_number,
            'start_at' => $appointment->start_at?->toISOString(),
            'end_at' => $appointment->end_at?->toISOString(),
            'workers' => $appointment->workers->map(fn ($worker) => [
                'id' => $worker->id,
                'name' => $worker->name,
            ])->all(),
            'status' => $appointment->status ? [
                'name' => $appointment->status->name,
                'color' => $appointment->status->color,
                'stage' => $appointment->status->stage,
            ] : null,
            'url' => route('calendar.index', ['appointment' => $appointment->id]),
        ];
    }

    /**
     * The recurring series this record is on, if any: the rule, when it stops,
     * and how many visits it has produced so far.
     *
     * The most recently started series wins — that is the rule in force.
     *
     * @param  array<int, int>  $contractIds
     * @return array<string, mixed>|null
     */
    public function series(array $contractIds): ?array
    {
        if ($contractIds === []) {
            return null;
        }

        $parent = Appointment::query()
            ->whereIn('contract_id', $contractIds)
            ->whereNull('parent_id')
            ->whereNotNull('recurrence_type')
            ->withCount('occurrences')
            ->orderByDesc('start_at')
            ->first();

        if (! $parent) {
            return null;
        }

        return [
            'appointment_id' => $parent->id,
            'recurrence_type' => $parent->recurrence_type,
            'recurrence_interval' => $parent->recurrence_interval,
            'recurrence_end' => $parent->recurrence_end?->toDateString(),
            'started_at' => $parent->start_at?->toISOString(),
            'occurrences' => (int) $parent->occurrences_count + 1,
            'url' => route('calendar.index', ['appointment' => $parent->id]),
        ];
    }

    /** One printable address line, or null when there is nothing to print. */
    public function address(?string $street, ?string $zip, ?string $city): ?string
    {
        $locality = trim(implode(' ', array_filter([$zip, $city])));
        $parts = array_filter([trim((string) $street), $locality]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** A "navigate there" link, so the address is a route and not just text. */
    public function mapUrl(?string $address): ?string
    {
        if (! $address) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($address);
    }
}
