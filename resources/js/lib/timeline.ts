import { hourMinute } from '@/lib/date-utils';
import type { TimelineAction, TimelineEvent, TimelineEventType } from '@/types';

/**
 * The record timeline's vocabulary and grouping, kept out of the component so
 * both detail pages read the same history the same way — and so the grouping
 * can be tested without mounting anything.
 */
export const TIMELINE_TYPES: TimelineEventType[] = ['appointment', 'comment', 'document', 'activity'];

/** Filter-chip wording. Plural, because a chip counts things. */
export const TIMELINE_LABELS: Record<TimelineEventType, string> = {
    appointment: 'Appointments',
    comment: 'Comments',
    document: 'Documents',
    activity: 'Changes',
};

/** Icon-chip colours, one per event type. */
export const TIMELINE_TONE: Record<TimelineEventType, string> = {
    appointment: 'bg-navy-wash text-navy',
    comment: 'bg-info-wash text-info',
    document: 'bg-muted text-muted-foreground',
    activity: 'bg-warning-wash text-warning',
};

/** What an `activity` event says it did. */
export const TIMELINE_ACTION_LABELS: Record<TimelineAction, string> = {
    created: 'Appointment created',
    updated: 'Appointment updated',
    deleted: 'Appointment deleted',
};

export interface TimelineTypeCount {
    type: TimelineEventType;
    count: number;
}

export interface TimelineDayGroup {
    key: string;
    label: string;
    events: TimelineEvent[];
}

/** How many events of each type are in the list — only types that occur. */
export function countByType(events: TimelineEvent[]): TimelineTypeCount[] {
    return TIMELINE_TYPES.map((type) => ({
        type,
        count: events.filter((event) => event.type === type).length,
    })).filter((entry) => entry.count > 0);
}

export function filterByType(events: TimelineEvent[], type: TimelineEventType | 'all'): TimelineEvent[] {
    return type === 'all' ? events : events.filter((event) => event.type === type);
}

/**
 * Newest day first, events inside a day newest first. Grouping by day is what
 * turns a list of rows into a story someone can skim.
 */
export function groupByDay(events: TimelineEvent[]): TimelineDayGroup[] {
    const groups = new Map<string, TimelineEvent[]>();

    for (const event of [...events].sort((a, b) => b.at.localeCompare(a.at))) {
        const date = new Date(event.at);
        const key = Number.isNaN(date.getTime())
            ? 'unknown'
            : `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

        const bucket = groups.get(key);
        if (bucket) bucket.push(event);
        else groups.set(key, [event]);
    }

    return [...groups.entries()].map(([key, dayEvents]) => ({
        key,
        label: dayLabel(key),
        events: dayEvents,
    }));
}

function dayLabel(key: string): string {
    if (key === 'unknown') return '';

    return new Date(`${key}T00:00:00`).toLocaleDateString('de-DE', {
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/** "14:30" — the time of day an event happened. */
export function eventTime(event: TimelineEvent): string {
    return hourMinute(event.at);
}
