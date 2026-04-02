<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $fillable = [
        'client_id',
        'employee_id',
        'title',
        'start_at',
        'end_at',
        'type',
        'notes',
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
            'recurrence_end' => 'date',
        ];
    }

    public const TYPES = [
        'service' => 'Servicetermin',
        'initial_visit' => 'Erstbegehung',
        'completed' => 'Abgeschlossen',
        'follow_up' => 'Nachkontrolle',
        'consultation' => 'Beratung',
        'documentation' => 'Dokumentation',
    ];

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
