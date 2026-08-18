export interface User {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    locale: 'de' | 'en';
    email_verified_at?: string;
    role: 'owner' | 'employee';
    company_id: number;
    permissions: string[];
}

export interface Employee {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    permissions: string[];
}

export interface Status {
    id: number;
    name: string;
    color: string;
    sort_order: number;
}

export interface Company {
    id: number;
    name: string;
    street: string | null;
    zip: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    place_id: string | null;
    phone: string | null;
    email: string | null;
    logo_path: string | null;
}

export interface CalendarSettings {
    show_weekends: boolean;
    start_hour: number;
    end_hour: number;
}

export type AppointmentKind = 'ohne_termin' | 'kundentermin';

export type RecurrenceType = 'weekly' | 'biweekly' | 'monthly' | 'custom';

export interface Client {
    id: number;
    salutation: string | null;
    first_name: string;
    last_name: string;
    company_name: string | null;
    name: string;
    billing_name: string | null;
    street: string | null;
    zip: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    place_id: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
}

export interface Contract {
    id: number;
    contract_number: string;
    title: string;
    kind: AppointmentKind | null;
    description: string | null;
    street: string | null;
    zip: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    place_id: string | null;
    clients: { id: number; name: string; first_name: string; last_name: string; company_name: string | null }[];
}

export interface ChecklistItem {
    text: string;
    checked: boolean;
}

export interface Appointment {
    id: number;
    contract: {
        id: number;
        contract_number: string;
        title: string;
        kind: AppointmentKind | null;
        street: string | null;
        zip: string | null;
        city: string | null;
        clients?: { id: number; first_name: string; last_name: string; company_name: string | null; name: string }[];
    } | null;
    workers: { id: number; first_name: string; last_name: string; name: string }[];
    start_at: string;
    end_at: string;
    status: { id: number; name: string; color: string } | null;
    notes: string | null;
    checklist: ChecklistItem[] | null;
    recurrence_type: RecurrenceType | null;
    recurrence_interval: number | null;
    recurrence_end: string | null;
    parent_id: number | null;
    comments?: Comment[];
    attachments?: AppointmentAttachment[];
}

export interface AppointmentAttachment {
    id: number;
    appointment_id: number;
    comment_id: number | null;
    user_id: number | null;
    user?: { id: number; first_name: string; last_name: string } | null;
    original_name: string;
    mime_type: string | null;
    size: number;
    url: string;
    created_at: string;
}

export interface Comment {
    id: number;
    appointment_id: number;
    user_id: number | null;
    user: { id: number; first_name: string; last_name: string } | null;
    body: string;
    created_at: string;
    updated_at: string;
    attachments?: AppointmentAttachment[];
}

export interface ActivityLog {
    id: number;
    appointment_id: number | null;
    user_id: number | null;
    user: { id: number; first_name: string; last_name: string } | null;
    appointment: { id: number; contract_id: number; contract: { id: number; title: string } | null } | null;
    comment: { id: number; body: string } | null;
    action: 'created' | 'updated' | 'deleted' | 'comment_added';
    changes: Record<string, { old: string | null; new: string | null }> | null;
    description: string | null;
    created_at: string;
}

export interface ClientStats {
    totalContracts: number;
    totalAppointments: number;
    upcomingAppointments: number;
    lastAppointment: string | null;
    nextAppointment: string | null;
}

export interface ClientComment {
    id: number;
    appointment_id: number;
    user_id: number | null;
    user: { id: number; first_name: string; last_name: string } | null;
    body: string;
    created_at: string;
    updated_at: string;
    appointment: { id: number; contract_id: number; contract: { id: number; title: string } | null } | null;
}

export interface PlaceSuggestion {
    placeId: string;
    description: string;
    street: string;
    zip: string;
    city: string;
    latitude: number;
    longitude: number;
}

export interface SharedCompany {
    name: string;
    logo_url: string | null;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

/* ---------------- global search ---------------- */

export type SearchItemType = 'client' | 'contract' | 'appointment' | 'action';

export interface SearchItem {
    id: number | string;
    type: SearchItemType;
    title: string;
    subtitle: string | null;
    url: string;
    icon?: string;
}

export interface SearchGroup {
    key: 'clients' | 'contracts' | 'appointments' | 'actions';
    label: string;
    items: SearchItem[];
}

export interface SearchResponse {
    query: string;
    groups: SearchGroup[];
}

/* ---------------- notifications ---------------- */

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

/* ---------------- staff roles ---------------- */

export interface StaffRole {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    permissions: string[];
}

/* ---------------- dashboard ---------------- */

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

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    companyBranding: SharedCompany | null;
    googlePlacesApiKey: string | null;
    locale: string;
    translations: Record<string, string>;
};
