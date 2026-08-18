/**
 * The client and contract detail pages: identity header, timeline and rail facts.
 */

import type { RecurrenceType } from './domain';
import type { PipelineStage } from './dashboard';

/** Every stage a status can carry, including the terminal one. */
export type StatusStage = PipelineStage['stage'] | 'cancelled';

export type RecordBadgeTone = 'navy' | 'success' | 'warning' | 'danger' | 'muted';

/** A derived label in a record's identity header. The key resolves to a translation. */
export interface RecordBadge {
    key: string;
    tone: RecordBadgeTone;
}

/** One compact figure in the identity header. */
export interface RecordStat {
    key: string;
    label: string;
    value: string;
}

export type TimelineEventType = 'appointment' | 'comment' | 'document' | 'activity';

export type TimelineAction = 'created' | 'updated' | 'deleted';

/** One entry of a record's merged history. */
export interface TimelineEvent {
    id: string;
    type: TimelineEventType;
    action: TimelineAction | null;
    at: string;
    title: string;
    excerpt: string | null;
    actor: string | null;
    url: string | null;
    status: { name: string; color: string; stage: StatusStage } | null;
    /** Which fields an `activity` event changed. */
    fields: string[];
}

export interface NextAppointmentFact {
    id: number;
    title: string;
    contract_number: string | null;
    start_at: string;
    end_at: string;
    workers: { id: number; name: string }[];
    status: { name: string; color: string; stage: StatusStage } | null;
    url: string;
}

/** The recurrence rule a record is currently on. */
export interface SeriesFact {
    appointment_id: number;
    recurrence_type: RecurrenceType | null;
    recurrence_interval: number | null;
    recurrence_end: string | null;
    started_at: string | null;
    occurrences: number;
    url: string;
}

export interface ClientFacts {
    since: string | null;
    address: string | null;
    map_url: string | null;
    access_notes: string | null;
    badges: RecordBadge[];
    stats: {
        contracts: number;
        open_contracts: number;
        appointments: number;
        upcoming: number;
        last: string | null;
        next: string | null;
    };
    next_appointment: NextAppointmentFact | null;
    series: SeriesFact | null;
}

export interface ContractFacts {
    since: string | null;
    address: string | null;
    map_url: string | null;
    access_notes: string | null;
    stage: PipelineStage['stage'] | null;
    progress: ContractProgress;
    team: { id: number; name: string }[];
    badges: RecordBadge[];
    stats: {
        appointments: number;
        upcoming: number;
        clients: number;
        last: string | null;
        next: string | null;
    };
    next_appointment: NextAppointmentFact | null;
    series: SeriesFact | null;
}
