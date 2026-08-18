/**
 * The notification bell.
 */

export type NotificationType =
    | 'comment_mention'
    | 'comment_added'
    | 'appointment_assigned'
    | 'appointment_unassigned'
    | 'schedule_conflict'
    | 'series_ending'
    | 'attachment_added';

export type NotificationSeverity = 'critical' | 'warning' | 'success' | 'info';

export interface AppNotification {
    id: number;
    type: NotificationType;
    severity: NotificationSeverity;
    actor: string | null;
    appointment_id: number | null;
    appointment_title: string | null;
    appointment_start_at: string | null;
    url: string | null;
    data: { title?: string; excerpt?: string; start_at?: string; conflicts_with?: { id: number; title: string | null }[] };
    read_at: string | null;
    created_at: string;
}

export interface NotificationResponse {
    notifications: AppNotification[];
    unread: number;
}
