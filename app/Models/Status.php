<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'color',
        'stage',
        'sort_order',
    ];

    /*
     * The name is free text the company owns; `stage` is the machine-readable
     * meaning the app reasons about (pipeline columns, "ready to invoice" counts).
     */
    public const STAGE_UNCONFIRMED = 'unconfirmed';

    public const STAGE_ACTIVE = 'active';

    public const STAGE_READY_TO_INVOICE = 'ready_to_invoice';

    public const STAGE_INVOICED = 'invoiced';

    public const STAGE_CANCELLED = 'cancelled';

    public const STAGES = [
        self::STAGE_UNCONFIRMED,
        self::STAGE_ACTIVE,
        self::STAGE_READY_TO_INVOICE,
        self::STAGE_INVOICED,
        self::STAGE_CANCELLED,
    ];

    /** Ordered stages for the pipeline board. Cancelled is not a pipeline column. */
    public const PIPELINE_STAGES = [
        self::STAGE_UNCONFIRMED,
        self::STAGE_ACTIVE,
        self::STAGE_READY_TO_INVOICE,
        self::STAGE_INVOICED,
    ];

    /** Stages that mean the work itself is finished. */
    public const COMPLETED_STAGES = [
        self::STAGE_READY_TO_INVOICE,
        self::STAGE_INVOICED,
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function isCompleted(): bool
    {
        return in_array($this->stage, self::COMPLETED_STAGES, true);
    }

    public function scopeInStage(Builder $query, string|array $stage): Builder
    {
        return $query->whereIn('stage', (array) $stage);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
