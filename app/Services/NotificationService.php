<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Creates the in-app notifications shown in the top-bar bell and the
 * dashboard's attention panel.
 *
 * Notifications are never raised for the person who caused them.
 */
class NotificationService
{
    public function __construct(private ScheduleConflictService $conflicts) {}

    /**
     * Raise a notification, collapsing repeats for standing conditions
     * (a conflict is one problem, not one per save).
     *
     * @param  array<string, mixed>  $data
     */
    public function raise(
        User $recipient,
        string $type,
        ?User $actor = null,
        ?Appointment $appointment = null,
        array $data = [],
    ): ?Notification {
        if ($actor && $actor->id === $recipient->id) {
            return null;
        }

        $attributes = [
            'company_id' => $recipient->company_id,
            'actor_id' => $actor?->id,
            'contract_id' => $appointment?->contract_id,
            'data' => $data,
            'read_at' => null,
            'updated_at' => now(),
        ];

        if (in_array($type, Notification::DEDUPED_TYPES, true)) {
            return Notification::withoutGlobalScope('company')->updateOrCreate(
                [
                    'user_id' => $recipient->id,
                    'type' => $type,
                    'appointment_id' => $appointment?->id,
                ],
                $attributes,
            );
        }

        return Notification::withoutGlobalScope('company')->create([
            ...$attributes,
            'user_id' => $recipient->id,
            'type' => $type,
            'appointment_id' => $appointment?->id,
        ]);
    }

    /**
     * Notify workers newly added to an appointment, and those removed from it.
     *
     * @param  array<int, int>  $before  worker ids before the change
     * @param  array<int, int>  $after  worker ids after the change
     */
    public function appointmentAssignmentsChanged(
        Appointment $appointment,
        array $before,
        array $after,
        ?User $actor = null,
    ): void {
        $added = array_diff($after, $before);
        $removed = array_diff($before, $after);

        $title = $appointment->contract?->title;

        foreach (User::whereIn('id', $added)->get() as $user) {
            $this->raise($user, Notification::TYPE_ASSIGNED, $actor, $appointment, [
                'title' => $title,
                'start_at' => $appointment->start_at?->toIso8601String(),
            ]);
        }

        foreach (User::whereIn('id', $removed)->get() as $user) {
            $this->raise($user, Notification::TYPE_UNASSIGNED, $actor, $appointment, [
                'title' => $title,
                'start_at' => $appointment->start_at?->toIso8601String(),
            ]);
        }
    }

    /**
     * Warn the affected technicians (and the owner) that an appointment
     * double-books someone.
     */
    public function appointmentConflicts(Appointment $appointment, ?User $actor = null): int
    {
        $clashes = $this->conflicts->conflictsFor($appointment);

        if ($clashes->isEmpty()) {
            // The clash may have been resolved by this very edit.
            Notification::withoutGlobalScope('company')
                ->where('appointment_id', $appointment->id)
                ->where('type', Notification::TYPE_CONFLICT)
                ->delete();

            return 0;
        }

        $appointment->loadMissing('workers', 'contract');
        $recipients = $appointment->workers->keyBy('id');

        // The owner should see conflicts even when not personally assigned.
        $owner = User::withoutGlobalScope('company')
            ->where('company_id', $appointment->company_id)
            ->where('role', User::ROLE_OWNER)
            ->first();
        if ($owner) {
            $recipients->put($owner->id, $owner);
        }

        foreach ($recipients as $user) {
            $this->raise($user, Notification::TYPE_CONFLICT, null, $appointment, [
                'title' => $appointment->contract?->title,
                'start_at' => $appointment->start_at?->toIso8601String(),
                'conflicts_with' => $clashes->map(fn (Appointment $c) => [
                    'id' => $c->id,
                    'title' => $c->contract?->title,
                ])->values()->all(),
            ]);
        }

        return $clashes->count();
    }

    /**
     * Notify the people watching an appointment that a comment was posted,
     * and separately notify anyone @mentioned in it.
     */
    public function commentPosted(Comment $comment, ?User $actor = null): void
    {
        $appointment = $comment->appointment;
        if (! $appointment) {
            return;
        }

        $appointment->loadMissing('workers', 'contract');

        $mentioned = $this->resolveMentions($comment->body, $appointment->company_id);
        $mentionedIds = $mentioned->pluck('id')->all();

        foreach ($mentioned as $user) {
            $this->raise($user, Notification::TYPE_MENTION, $actor, $appointment, [
                'title' => $appointment->contract?->title,
                'excerpt' => $this->excerpt($comment->body),
            ]);
        }

        // Watchers = assigned workers + whoever created the appointment.
        $watchers = $appointment->workers->keyBy('id');
        if ($appointment->created_by) {
            $creator = User::find($appointment->created_by);
            if ($creator) {
                $watchers->put($creator->id, $creator);
            }
        }

        foreach ($watchers as $user) {
            if (in_array($user->id, $mentionedIds, true)) {
                continue; // already told, more specifically
            }

            $this->raise($user, Notification::TYPE_COMMENT, $actor, $appointment, [
                'title' => $appointment->contract?->title,
                'excerpt' => $this->excerpt($comment->body),
            ]);
        }
    }

    /**
     * Match `@Vorname Nachname` and `@Vorname` against company staff.
     *
     * @return Collection<int, User>
     */
    public function resolveMentions(string $body, int $companyId): Collection
    {
        preg_match_all('/@([\p{L}]+(?:\s+[\p{L}]+)?)/u', $body, $matches);

        if (empty($matches[1])) {
            return collect();
        }

        // "@Lisa kannst du das machen?" captures "Lisa kannst", so offer both the
        // two-word reading (full name) and the first word alone (first name).
        $candidates = [];
        foreach ($matches[1] as $match) {
            $match = mb_strtolower(trim($match));
            $candidates[] = $match;

            $firstWord = preg_split('/\s+/u', $match)[0] ?? null;
            if ($firstWord !== null && $firstWord !== $match) {
                $candidates[] = $firstWord;
            }
        }
        $candidates = array_values(array_unique($candidates));

        return User::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->get()
            ->filter(function (User $user) use ($candidates) {
                $full = mb_strtolower($user->name);
                $first = mb_strtolower($user->first_name);

                foreach ($candidates as $candidate) {
                    if ($candidate === $full || $candidate === $first) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    private function excerpt(string $body, int $length = 120): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');

        return mb_strlen($body) > $length ? mb_substr($body, 0, $length - 1).'…' : $body;
    }
}
