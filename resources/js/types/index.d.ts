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
    client: { id: number; first_name: string; last_name: string; company_name: string | null; name: string } | null;
    employee: { id: number; first_name: string; last_name: string; name: string } | null;
    start_at: string;
    end_at: string;
    status: { id: number; name: string; color: string } | null;
    kind: AppointmentKind | null;
    notes: string | null;
    checklist: ChecklistItem[] | null;
    recurrence_type: RecurrenceType | null;
    recurrence_interval: number | null;
    recurrence_end: string | null;
    parent_id: number | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    locale: string;
    translations: Record<string, string>;
};
