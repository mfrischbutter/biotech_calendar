import type { PipelineStage } from '@/types';

export type Stage = PipelineStage['stage'] | 'cancelled';

/**
 * Single source of truth for how a status *stage* is presented.
 *
 * The status name is free text the company owns; the stage is the meaning the
 * app reasons about. Mirrors `App\Models\Status::STAGE_*`.
 */
export const STAGE_LABELS: Record<Stage, string> = {
    unconfirmed: 'Not confirmed',
    active: 'In progress',
    ready_to_invoice: 'Ready to invoice',
    invoiced: 'Invoiced',
    cancelled: 'Cancelled',
};

/** Tailwind background classes for the small stage dot. */
export const STAGE_DOT: Record<Stage, string> = {
    unconfirmed: 'bg-danger',
    active: 'bg-warning',
    ready_to_invoice: 'bg-success',
    invoiced: 'bg-muted-foreground',
    cancelled: 'bg-muted-foreground',
};

/** Wash + text classes for a stage pill. */
export const STAGE_PILL: Record<Stage, string> = {
    unconfirmed: 'bg-danger-wash text-danger',
    active: 'bg-warning-wash text-warning',
    ready_to_invoice: 'bg-success-wash text-success-foreground',
    invoiced: 'bg-muted text-muted-foreground',
    cancelled: 'bg-muted text-muted-foreground',
};

export const PIPELINE_ORDER: Stage[] = [
    'unconfirmed',
    'active',
    'ready_to_invoice',
    'invoiced',
];
