/**
 * Turning a recurrence rule into the words the chip shows. Kept out of the
 * components so the wording is testable and identical everywhere.
 */

/** A translation key plus its placeholders — the caller runs it through `t()`. */
export interface RecurrenceDescriptor {
    key: string;
    params?: Record<string, string>;
}

export const NO_RECURRENCE: RecurrenceDescriptor = { key: 'Does not repeat' };

/**
 * `interval` only matters for the `custom` rule, where it counts weeks.
 */
export function recurrenceDescriptor(
    isRecurring: boolean,
    type: string,
    interval: number,
): RecurrenceDescriptor {
    if (!isRecurring) return NO_RECURRENCE;

    switch (type) {
        case 'weekly':
            return { key: 'Weekly' };
        case 'biweekly':
            return { key: 'Every 2 weeks' };
        case 'monthly':
            return { key: 'Monthly' };
        case 'custom': {
            const weeks = Math.max(1, Math.round(interval || 1));
            if (weeks === 1) return { key: 'Weekly' };
            if (weeks === 2) return { key: 'Every 2 weeks' };
            return { key: 'Every :count weeks', params: { count: String(weeks) } };
        }
        default:
            return NO_RECURRENCE;
    }
}
