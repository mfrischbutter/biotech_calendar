/**
 * The dashboard's strips: today's schedule, the attention counts and workload.
 */

export type ScheduleState = 'done' | 'now' | 'upcoming' | 'past';

export interface ScheduleItem {
    id: number;
    title: string | null;
    start_at: string;
    end_at: string;
    address: string;
    status: { name: string; color: string; stage: string } | null;
    state: ScheduleState;
}

export interface ScheduleGroup {
    worker_id: number | null;
    worker_name: string | null;
    items: ScheduleItem[];
}

export interface AttentionCounts {
    unassigned: number;
    overdue: number;
    readyToInvoice: number;
    utilisation: number;
    conflicts: number;
}

export interface PipelineStage {
    stage: 'unconfirmed' | 'active' | 'ready_to_invoice' | 'invoiced';
    count: number;
}

export interface WorkloadRow {
    id: number;
    name: string;
    appointments: number;
    percent: number;
}

export interface AttentionNotification {
    id: number;
    type: NotificationType;
    severity: NotificationSeverity;
    actor: string | null;
    title: string | null;
    excerpt: string | null;
    url: string | null;
    created_at: string;
}

export interface DashboardActivity {
    id: number;
    action: 'created' | 'updated' | 'deleted' | 'comment_added';
    user: string | null;
    title: string | null;
    url: string | null;
    created_at: string;
}
