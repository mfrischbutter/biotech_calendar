<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An in-app notification addressed to one user.
 *
 * Stored in `app_notifications` to stay clear of Laravel's own notifications
 * table. Rendered by the bell in the top bar and the dashboard attention panel.
 */
class Notification extends Model
{
    use BelongsToCompany;

    protected $table = 'app_notifications';

    protected $fillable = [
        'company_id',
        'user_id',
        'actor_id',
        'type',
        'appointment_id',
        'contract_id',
        'client_id',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public const TYPE_MENTION = 'comment_mention';

    public const TYPE_COMMENT = 'comment_added';

    public const TYPE_ASSIGNED = 'appointment_assigned';

    public const TYPE_UNASSIGNED = 'appointment_unassigned';

    public const TYPE_CONFLICT = 'schedule_conflict';

    public const TYPE_SERIES_ENDING = 'series_ending';

    public const TYPE_ATTACHMENT = 'attachment_added';

    public const TYPES = [
        self::TYPE_MENTION,
        self::TYPE_COMMENT,
        self::TYPE_ASSIGNED,
        self::TYPE_UNASSIGNED,
        self::TYPE_CONFLICT,
        self::TYPE_SERIES_ENDING,
        self::TYPE_ATTACHMENT,
    ];

    /** Types that describe a standing condition, so re-raising should update in place. */
    public const DEDUPED_TYPES = [
        self::TYPE_ASSIGNED,
        self::TYPE_UNASSIGNED,
        self::TYPE_CONFLICT,
        self::TYPE_SERIES_ENDING,
    ];

    /** Severity drives the colour of the badge in the UI. */
    public function severity(): string
    {
        return match ($this->type) {
            self::TYPE_CONFLICT => 'critical',
            self::TYPE_UNASSIGNED, self::TYPE_SERIES_ENDING => 'warning',
            self::TYPE_ATTACHMENT => 'success',
            default => 'info',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
