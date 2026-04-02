export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'owner' | 'employee';
}

export interface Employee {
    id: number;
    name: string;
    email: string;
    permissions: string[];
}

export type AppointmentType = 'service' | 'initial_visit' | 'completed' | 'follow_up' | 'consultation' | 'documentation';
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

export interface Appointment {
    id: number;
    title: string;
    client: { id: number; name: string } | null;
    employee: { id: number; name: string } | null;
    start_at: string;
    end_at: string;
    type: AppointmentType;
    notes: string | null;
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
