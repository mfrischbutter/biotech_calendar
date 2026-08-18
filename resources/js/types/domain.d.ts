/**
 * Core domain models — the shapes the server sends for the records the app is about.
 */

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
    /** Preset role the employee was given, if any. */
    staff_role: { id: number; slug: string; name: string } | null;
    /** True when the granted permissions no longer match the preset. */
    has_custom_permissions: boolean;
    appointments_this_week: number;
    /** Booked share of a 40 hour week, 0–100. */
    utilisation: number;
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

/** One entry of the vertical settings rail. */
export type SettingsSection = 'company' | 'branding' | 'calendar' | 'statuses' | 'checklists';

export type AppointmentKind = 'ohne_termin' | 'kundentermin';

export type RecurrenceType = 'weekly' | 'biweekly' | 'monthly' | 'custom';

/** Which appointments an edit is written to. */
export type SeriesScope = 'single' | 'series';

/** A reusable checklist offered in the appointment form and managed in Settings. */
export interface ChecklistTemplate {
    id: number;
    name: string;
    kind: AppointmentKind | null;
    items: string[];
}

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
    /** Site access details — key safe, gate code, "ring next door". */
    access_notes: string | null;
}

/** The slim client shape that pickers and selects are handed. */
export interface ClientOption {
    id: number;
    first_name: string;
    last_name: string;
    company_name: string | null;
    name: string;
}

export interface Contract {
    id: number;
    contract_number: string;
    title: string;
    kind: AppointmentKind | null;
    description: string | null;
    access_notes: string | null;
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


export interface StaffRole {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    permissions: string[];
}
