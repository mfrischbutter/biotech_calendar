export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'owner' | 'employee';
    company_id: number;
    permissions: string[];
}

export interface Employee {
    id: number;
    name: string;
    email: string;
    permissions: string[];
}

export interface Tag {
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

export type RecurrenceType = 'weekly' | 'biweekly' | 'monthly' | 'custom';

export interface Client {
    id: number;
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
    client: { id: number; name: string } | null;
    employee: { id: number; name: string } | null;
    start_at: string;
    end_at: string;
    tag: { id: number; name: string; color: string } | null;
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
