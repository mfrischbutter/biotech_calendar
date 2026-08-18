import type { CalendarEmployee, CalendarFilters } from '@/types';

export interface CalendarBoardRows {
    /** The people who get a row on the team board. */
    employees: CalendarEmployee[];
    /** Whether the row for work nobody owns belongs on the board. */
    showUnassigned: boolean;
}

/**
 * Which rows the team board draws under the current filter.
 *
 * The board used to draw every colleague even while the filter was showing one
 * person's work, so the other rows sat there empty and read as "nothing booked"
 * rather than as "you filtered them out".
 *
 * The rows mirror exactly the union the server narrowed the appointments by
 * (`CalendarQuery::applyFilters`): the chosen people, plus the unassigned row
 * when it was chosen. With nothing chosen, the whole team stays on the board.
 */
export function calendarBoardRows(
    employees: CalendarEmployee[],
    filters: CalendarFilters,
): CalendarBoardRows {
    const narrowed = filters.employees.length > 0 || filters.unassigned;

    if (!narrowed) {
        return { employees, showUnassigned: true };
    }

    return {
        employees: employees.filter((employee) => filters.employees.includes(employee.id)),
        showUnassigned: filters.unassigned,
    };
}
