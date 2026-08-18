<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use BelongsToCompany, HasFactory;

    public const KINDS = ['ohne_termin', 'kundentermin'];

    protected $fillable = [
        'company_id',
        'contract_number',
        'title',
        'kind',
        'description',
        'access_notes',
        'street',
        'zip',
        'city',
        'latitude',
        'longitude',
        'place_id',
    ];

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'contract_client')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * How far along the job is: visits that reached a completed status, out of
     * the visits still counting towards the job.
     *
     * Cancelled visits leave the denominator. A one-visit job that was called
     * off used to read "0/1" with an empty bar — the same picture as a job
     * nobody has started, when in truth there is no work left on it at all.
     *
     * Relies on the counts `scopeWithProgressCounts()` loads when the caller
     * has them, so lists stay a single query.
     *
     * @return array{done: int, total: int, percent: int}
     */
    public function progress(): array
    {
        $total = $this->live_appointments_count ?? $this->appointments()
            ->whereDoesntHave('status', fn ($q) => $q->where('stage', Status::STAGE_CANCELLED))
            ->count();
        $done = $this->completed_appointments_count ?? $this->appointments()
            ->whereHas('status', fn ($q) => $q->whereIn('stage', Status::COMPLETED_STAGES))
            ->count();

        return [
            'done' => (int) $done,
            'total' => (int) $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /**
     * Eager-load what `progress()` prefers.
     *
     * `appointments_count` stays every visit ever booked — it is what the list
     * sorts by and what the detail screen reports — so the progress denominator
     * gets a count of its own rather than redefining that one.
     */
    public function scopeWithProgressCounts(Builder $query): Builder
    {
        return $query->withCount([
            'appointments',
            'appointments as live_appointments_count' => fn ($q) => $q->whereDoesntHave(
                'status',
                fn ($s) => $s->where('stage', Status::STAGE_CANCELLED)
            ),
            'appointments as completed_appointments_count' => fn ($q) => $q->whereHas(
                'status',
                fn ($s) => $s->whereIn('stage', Status::COMPLETED_STAGES)
            ),
        ]);
    }
}
