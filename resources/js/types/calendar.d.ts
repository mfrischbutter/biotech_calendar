/**
 * The calendar board: which view, which slice of the team, how densely packed.
 */

export type CalendarView = 'day' | 'week' | 'month' | 'team-week';

/** A calendar person: an employee row, or the pseudo-row for unassigned work. */
export interface CalendarEmployee {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
}

/** The filter rail's state, mirrored in the query string. */
export interface CalendarFilters {
    employees: number[];
    statuses: number[];
    unassigned: boolean;
    conflicts: boolean;
}

/**
 * What a `?new=1` link handed the create form. Mirrors
 * `CalendarQuery::createDefaultsFrom()`.
 */
export interface CalendarCreateDefaults {
    /** The job the link named, when it named one. */
    contractId: number | null;
    /** The customer it named instead, to narrow the job picker with. */
    clientName: string | null;
}

/** How tightly the team board packs its rows. Mirrored in the query string. */
export type CalendarDensity = 'compact' | 'detailed';

/** appointment id → the ids of the appointments it double-books against. */
export type ConflictMap = Record<number, number[]>;

export interface CalendarEmployeeTotal {
    /** `null` is the unassigned row. */
    id: number | null;
    appointments: number;
    minutes: number;
}

export interface CalendarDayTotal {
    date: string;
    appointments: number;
    minutes: number;
}

export interface CalendarTotals {
    perEmployee: CalendarEmployeeTotal[];
    perDay: CalendarDayTotal[];
}
