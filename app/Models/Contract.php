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
     * How far along the job is: appointments whose status counts as completed,
     * out of all appointments planned for the contract.
     *
     * Relies on `appointments_count` / `completed_appointments_count` when the
     * caller has eager-loaded them, so lists stay a single query.
     *
     * @return array{done: int, total: int, percent: int}
     */
    public function progress(): array
    {
        $total = $this->appointments_count ?? $this->appointments()->count();
        $done = $this->completed_appointments_count ?? $this->appointments()
            ->whereHas('status', fn ($q) => $q->whereIn('stage', Status::COMPLETED_STAGES))
            ->count();

        return [
            'done' => (int) $done,
            'total' => (int) $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /** Eager-load the two counts `progress()` prefers. */
    public function scopeWithProgressCounts(Builder $query): Builder
    {
        return $query->withCount([
            'appointments',
            'appointments as completed_appointments_count' => fn ($q) => $q->whereHas(
                'status',
                fn ($s) => $s->whereIn('stage', Status::COMPLETED_STAGES)
            ),
        ]);
    }
}
