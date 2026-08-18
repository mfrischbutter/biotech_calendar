<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'contract_id',
        'start_at',
        'end_at',
        'status_id',
        'notes',
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

    public const RECURRENCE_TYPES = ['weekly', 'biweekly', 'monthly', 'custom'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'appointment_user')->withTimestamps();
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

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AppointmentAttachment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_type !== null;
    }

    public function isParent(): bool
    {
        return $this->parent_id === null && $this->recurrence_type !== null;
    }

    /**
     * Whether editing this appointment could affect siblings — true for the
     * series parent and for every generated occurrence.
     */
    public function belongsToSeries(): bool
    {
        return $this->parent_id !== null || $this->recurrence_type !== null;
    }

    /** The id the whole series hangs off: the parent's own id. */
    public function seriesRootId(): int
    {
        return $this->parent_id ?? $this->id;
    }

    /**
     * Parent plus every occurrence, in chronological order.
     *
     * @return Collection<int, self>
     */
    public function seriesMembers(): Collection
    {
        $rootId = $this->seriesRootId();

        return self::where(function ($query) use ($rootId) {
            $query->where('id', $rootId)->orWhere('parent_id', $rootId);
        })->orderBy('start_at')->get();
    }
}
