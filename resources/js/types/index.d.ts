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

export interface ChecklistItem {
    text: string;
    checked: boolean;
}

export interface Appointment {
    id: number;
    title: string;
    client: { id: number; first_name: string; last_name: string; company_name: string | null; name: string; street: string | null; zip: string | null; city: string | null } | null;
    employee: { id: number; first_name: string; last_name: string; name: string } | null;
    start_at: string;
    end_at: string;
    status: { id: number; name: string; color: string } | null;
    kind: AppointmentKind | null;
    notes: string | null;
    street: string | null;
    zip: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    place_id: string | null;
    checklist: ChecklistItem[] | null;
    recurrence_type: RecurrenceType | null;
    recurrence_interval: number | null;
    recurrence_end: string | null;
    parent_id: number | null;
    comments?: Comment[];
}

export interface Comment {
    id: number;
    appointment_id: number;
    user_id: number | null;
    user: { id: number; first_name: string; last_name: string } | null;
    body: string;
    created_at: string;
    updated_at: string;
}

export interface ActivityLog {
    id: number;
    appointment_id: number | null;
    user_id: number | null;
    user: { id: number; first_name: string; last_name: string } | null;
    appointment: { id: number; title: string } | null;
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
