/**
 * `?new=1` is the app's one convention for "arrive with the create form already
 * waiting" — the top-bar search actions, the "Termin planen" buttons on the
 * detail screens and the dashboard's primary button all link that way.
 *
 * Only the calendar ever honoured it, so "Neuer Kunde" and "Neuer Auftrag"
 * dropped the user on a list and left them to find the button themselves.
 */
export function wantsCreateForm(): boolean {
    return new URLSearchParams(window.location.search).get('new') === '1';
}
