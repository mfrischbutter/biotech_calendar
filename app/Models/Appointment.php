<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'client_id',
        'employee_id',
        'title',
        'start_at',
        'end_at',
        'status_id',
        'kind',
        'notes',
        'street',
        'zip',
        'city',
        'checklist',
        'created_by',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_end',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'checklist' => 'array',
            'recurrence_end' => 'date',
        ];
    }

    public const KINDS = ['ohne_termin', 'kundentermin'];

    public const RECURRENCE_TYPES = ['weekly', 'biweekly', 'monthly', 'custom'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_type !== null;
    }

    public function isParent(): bool
    {
        return $this->parent_id === null && $this->recurrence_type !== null;
    }
}
