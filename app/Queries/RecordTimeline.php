<?php

namespace App\Queries;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\AppointmentAttachment;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The chronological story of a record: the visits that were booked, what people
 * said about them, what they attached, and what changed.
 *
 * Everything hangs off appointments, so both the client page (many contracts)
 * and the contract page (one) can use it by handing over contract ids. The
 * appointment ids are resolved as a *subquery*, never as a fetched list, so the
 * query count is constant no matter how much history a record has.
 */
class RecordTimeline
{
    public const APPOINTMENT = 'appointment';

    public const COMMENT = 'comment';

    public const DOCUMENT = 'document';

    public const ACTIVITY = 'activity';

    /** How many events the page shows. Each source is capped at the same size. */
    public const LIMIT = 30;

    /**
     * Which changed columns are worth naming in the timeline, and the
     * translation key each is shown under.
     *
     * AppointmentObserver logs every dirty column verbatim, so without this
     * whitelist a raw name like `recurrence_interval` would reach the UI and be
     * rendered untranslated. Anything unlisted is dropped — the event still says
     * the appointment was updated, just not which internal column moved.
     * Every value here must exist as a key in lang/de.json.
     */
    public const FIELD_LABELS = [
        'start_at' => 'Start',
        'end_at' => 'End',
        'status' => 'Status',
        'status_id' => 'Status',
        'notes' => 'Notes',
        'checklist' => 'Checklist',
        'contract_id' => 'Contract',
        'client' => 'Client',
        'client_id' => 'Client',
        'employee' => 'Employee',
        'employee_id' => 'Employee',
        'recurrence_type' => 'Repeat',
        'recurrence_interval' => 'Repeat',
        'recurrence_end' => 'Series end',
        'is_recurring' => 'Repeat',
        'street' => 'Address',
        'zip' => 'Address',
        'city' => 'Address',
    ];

    /**
     * Translation keys for the columns this log touched, de-duplicated and with
     * anything unmapped dropped.
     *
     * @param  array<string, mixed>|null  $changes
     * @return array<int, string>
     */
    public static function changedFieldLabels(?array $changes): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn (string $field) => self::FIELD_LABELS[$field] ?? null,
                array_keys($changes ?? [])
            )
        )));
    }

    /**
     * @param  array<int, int>  $contractIds
     * @return array<int, array<string, mixed>>
     */
    public function forContracts(array $contractIds, int $limit = self::LIMIT): array
    {
        if ($contractIds === []) {
            return [];
        }

        $events = collect()
            ->concat($this->appointments($contractIds, $limit))
            ->concat($this->comments($contractIds, $limit))
            ->concat($this->documents($contractIds, $limit))
            ->concat($this->changes($contractIds, $limit));

        return $events
            ->sortByDesc('at')
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * Appointment ids as a subquery builder — cheap to reuse, never materialised.
     *
     * @param  array<int, int>  $contractIds
     */
    private function appointmentIds(array $contractIds): Builder
    {
        return Appointment::query()
            ->select('appointments.id')
            ->whereIn('appointments.contract_id', $contractIds);
    }

    /**
     * @param  array<int, int>  $contractIds
     * @return Collection<int, array<string, mixed>>
     */
    private function appointments(array $contractIds, int $limit): Collection
    {
        return Appointment::query()
            ->whereIn('contract_id', $contractIds)
            ->with([
                'contract:id,contract_number,title',
                'status:id,name,color,stage',
                'workers:id,first_name,last_name',
            ])
            ->orderByDesc('start_at')
            ->limit($limit)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => self::APPOINTMENT.'-'.$appointment->id,
                'type' => self::APPOINTMENT,
                'action' => null,
                'at' => $appointment->start_at?->toISOString(),
                'title' => $appointment->contract?->title ?? __('Appointment'),
                'excerpt' => $appointment->workers->pluck('name')->implode(', ') ?: null,
                'actor' => null,
                'url' => route('calendar.index', ['appointment' => $appointment->id]),
                'status' => $appointment->status ? [
                    'name' => $appointment->status->name,
                    'color' => $appointment->status->color,
                    'stage' => $appointment->status->stage,
                ] : null,
                'fields' => [],
            ]);
    }

    /**
     * @param  array<int, int>  $contractIds
     * @return Collection<int, array<string, mixed>>
     */
    private function comments(array $contractIds, int $limit): Collection
    {
        return Comment::query()
            ->whereIn('appointment_id', $this->appointmentIds($contractIds))
            ->with(['user:id,first_name,last_name', 'appointment:id,contract_id', 'appointment.contract:id,title'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Comment $comment) => [
                'id' => self::COMMENT.'-'.$comment->id,
                'type' => self::COMMENT,
                'action' => null,
                'at' => $comment->created_at?->toISOString(),
                'title' => $comment->appointment?->contract?->title ?? __('Appointment'),
                'excerpt' => $comment->body,
                'actor' => $comment->user?->name,
                'url' => $comment->appointment_id
                    ? route('calendar.index', ['appointment' => $comment->appointment_id])
                    : null,
                'status' => null,
                'fields' => [],
            ]);
    }

    /**
     * @param  array<int, int>  $contractIds
     * @return Collection<int, array<string, mixed>>
     */
    private function documents(array $contractIds, int $limit): Collection
    {
        return AppointmentAttachment::query()
            ->whereIn('appointment_id', $this->appointmentIds($contractIds))
            ->with(['user:id,first_name,last_name', 'appointment:id,contract_id', 'appointment.contract:id,title'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AppointmentAttachment $attachment) => [
                'id' => self::DOCUMENT.'-'.$attachment->id,
                'type' => self::DOCUMENT,
                'action' => null,
                'at' => $attachment->created_at?->toISOString(),
                'title' => $attachment->original_name,
                'excerpt' => $attachment->appointment?->contract?->title,
                'actor' => $attachment->user?->name,
                'url' => route('attachments.show', ['attachment' => $attachment->id]),
                'status' => null,
                'fields' => [],
            ]);
    }

    /**
     * Contract-level edits: what the observer recorded about the appointments.
     *
     * @param  array<int, int>  $contractIds
     * @return Collection<int, array<string, mixed>>
     */
    private function changes(array $contractIds, int $limit): Collection
    {
        return ActivityLog::query()
            ->whereIn('appointment_id', $this->appointmentIds($contractIds))
            ->where('action', '!=', 'comment_added')
            ->with(['user:id,first_name,last_name', 'appointment:id,contract_id', 'appointment.contract:id,title'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => self::ACTIVITY.'-'.$log->id,
                'type' => self::ACTIVITY,
                'action' => $log->action,
                'at' => $log->created_at?->toISOString(),
                'title' => $log->appointment?->contract?->title ?? __('Appointment'),
                'excerpt' => null,
                'actor' => $log->user?->name,
                'url' => $log->appointment_id
                    ? route('calendar.index', ['appointment' => $log->appointment_id])
                    : null,
                'status' => null,
                'fields' => self::changedFieldLabels($log->changes),
            ]);
    }
}
