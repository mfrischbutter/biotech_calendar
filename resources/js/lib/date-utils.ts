/** Extract hours*60+minutes in **local** time from a datetime string. */
export function localMinutes(dateStr: string): number {
    const d = new Date(dateStr);
    return d.getHours() * 60 + d.getMinutes();
}

/**
 * Build a UTC ISO string from a local date + time.
 * E.g. localToISO("2026-04-02", "14:00") in Berlin (UTC+2) → "2026-04-02T12:00:00.000Z"
 */
export function localToISO(date: string, time: string): string {
    return new Date(`${date}T${time}:00`).toISOString();
}

/**
 * Format a UTC datetime string into local "yyyy-MM-dd" and "HH:mm".
 */
export function isoToLocalParts(dateStr: string): { date: string; time: string } {
    const d = new Date(dateStr);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return { date: `${yyyy}-${mm}-${dd}`, time: `${hh}:${min}` };
}

/** Extract local date string from a datetime. */
export function localDateString(dateStr: string): string {
    return isoToLocalParts(dateStr).date;
}

/** Short, list-friendly German date, e.g. "12. Aug 2026". Null-safe. */
export function shortDate(dateStr: string | null): string | null {
    if (!dateStr) return null;

    const d = new Date(dateStr);
    if (Number.isNaN(d.getTime())) return null;

    return d.toLocaleDateString('de-DE', { day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * Month and year, e.g. "März 2024" — how long a record has been around, which
 * is the resolution both detail headers want ("Kunde seit …"). Null-safe.
 */
export function monthAndYear(dateStr: string | null | undefined): string | null {
    if (!dateStr) return null;

    const d = new Date(dateStr);
    if (Number.isNaN(d.getTime())) return null;

    return d.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
}

/** Local time of day, e.g. "14:30". Empty string for an unusable input. */
export function hourMinute(dateStr: string | null | undefined): string {
    if (!dateStr) return '';

    const d = new Date(dateStr);
    if (Number.isNaN(d.getTime())) return '';

    return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}
