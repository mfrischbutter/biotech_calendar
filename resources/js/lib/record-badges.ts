import { STAGE_LABELS, type Stage } from '@/lib/pipeline-stages';
import type { RecordBadge, RecordBadgeTone } from '@/types';

/**
 * How a derived identity badge is worded and coloured.
 *
 * The server decides *which* badges a record earns (that is a business rule);
 * this file decides how they look and read, so the wording lives in one place
 * for both detail pages. Pipeline stages are included so a contract's stage can
 * ride along in the same row of badges.
 */
export const RECORD_BADGE_LABELS: Record<string, string> = {
    ...STAGE_LABELS,
    active_contract: 'Active contract',
    new_client: 'New customer',
    dormant: 'Dormant',
    recurring: 'Recurring series',
    unassigned_work: 'Unassigned work',
    kundentermin: 'On-site work',
    ohne_termin: 'Work without appointment',
};

export const RECORD_BADGE_TONE: Record<RecordBadgeTone, string> = {
    navy: 'bg-navy-wash text-navy',
    success: 'bg-success-wash text-success-foreground',
    warning: 'bg-warning-wash text-warning',
    danger: 'bg-danger-wash text-danger',
    muted: 'bg-muted text-muted-foreground',
};

/** A pipeline stage as an identity badge. */
export const STAGE_BADGE_TONE: Record<Stage, RecordBadgeTone> = {
    unconfirmed: 'danger',
    active: 'warning',
    ready_to_invoice: 'success',
    invoiced: 'muted',
    cancelled: 'muted',
};

/** The translation key for a badge; unknown keys fall back to the key itself. */
export function badgeLabelKey(badge: RecordBadge): string {
    return RECORD_BADGE_LABELS[badge.key] ?? badge.key;
}

export function badgeToneClass(badge: RecordBadge): string {
    return RECORD_BADGE_TONE[badge.tone] ?? RECORD_BADGE_TONE.muted;
}
