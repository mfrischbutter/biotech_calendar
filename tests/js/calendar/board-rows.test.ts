import { describe, expect, it } from 'vitest';
import { calendarBoardRows } from '@/lib/calendar-rows';
import type { CalendarEmployee, CalendarFilters } from '@/types';

const employees: CalendarEmployee[] = [
    { id: 7, first_name: 'Anna', last_name: 'Berg', name: 'Anna Berg' },
    { id: 9, first_name: 'Max', last_name: 'Kern', name: 'Max Kern' },
];

function filters(overrides: Partial<CalendarFilters> = {}): CalendarFilters {
    return { employees: [], statuses: [], unassigned: false, conflicts: false, ...overrides };
}

describe('calendarBoardRows', () => {
    it('keeps the whole team on the board while nobody is filtered', () => {
        const rows = calendarBoardRows(employees, filters());

        expect(rows.employees).toEqual(employees);
        expect(rows.showUnassigned).toBe(true);
    });

    it('draws only the chosen people', () => {
        const rows = calendarBoardRows(employees, filters({ employees: [9] }));

        expect(rows.employees.map((e) => e.name)).toEqual(['Max Kern']);
        expect(rows.showUnassigned).toBe(false);
    });

    it('narrows to the unassigned row alone when that is all that was chosen', () => {
        const rows = calendarBoardRows(employees, filters({ unassigned: true }));

        expect(rows.employees).toEqual([]);
        expect(rows.showUnassigned).toBe(true);
    });

    it('mirrors the server union of people and unowned work', () => {
        const rows = calendarBoardRows(employees, filters({ employees: [7], unassigned: true }));

        expect(rows.employees.map((e) => e.name)).toEqual(['Anna Berg']);
        expect(rows.showUnassigned).toBe(true);
    });

    // Narrowing by status says nothing about who should have a row.
    it('leaves the rows alone when only the status filter is on', () => {
        const rows = calendarBoardRows(employees, filters({ statuses: [31] }));

        expect(rows.employees).toEqual(employees);
        expect(rows.showUnassigned).toBe(true);
    });

    it('keeps the team order rather than the click order', () => {
        const rows = calendarBoardRows(employees, filters({ employees: [9, 7] }));

        expect(rows.employees.map((e) => e.id)).toEqual([7, 9]);
    });
});
