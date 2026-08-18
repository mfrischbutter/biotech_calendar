import { mount, type MountingOptions } from '@vue/test-utils';
import type { Component } from 'vue';
import { pageState } from './setup';
import type { Appointment, Client, Status, User } from '@/types';

/** Replace the shared Inertia page props for the current test. */
export function setPageProps(props: Record<string, unknown>): void {
    pageState.props = {
        auth: { user: null },
        translations: {},
        locale: 'de',
        companyBranding: null,
        ...props,
    };
}

/** Reset page props to a signed-in owner with no translations (keys pass through). */
export function setAuthedOwner(overrides: Record<string, unknown> = {}): void {
    setPageProps({
        auth: {
            user: {
                id: 1,
                first_name: 'Bjoern',
                last_name: 'Wilhelmsen',
                name: 'Bjoern Wilhelmsen',
                email: 'b@wilhelmsen-biotec.de',
                role: 'owner',
                company_id: 1,
                permissions: [],
            },
        },
        ...overrides,
    });
}

export function mountComponent<T extends Component>(
    component: T,
    options: MountingOptions<Record<string, unknown>> = {},
) {
    return mount(component, {
        ...options,
        global: {
            stubs: { teleport: true, ...(options.global?.stubs ?? {}) },
            ...options.global,
        },
    } as MountingOptions<Record<string, unknown>>);
}

/* ------------------------------------------------------------------ */
/* Fixture builders — keep test data terse and intention-revealing.     */
/* ------------------------------------------------------------------ */

let seq = 0;
const nextId = () => ++seq;

export function makeStatus(overrides: Partial<Status> = {}): Status {
    return {
        id: nextId(),
        name: 'Erste Massnahme',
        color: '#F59E0B',
        sort_order: 1,
        ...overrides,
    } as Status;
}

export function makeWorker(overrides: Partial<User> = {}): User {
    const id = overrides.id ?? nextId();
    return {
        id,
        first_name: 'Markus',
        last_name: 'Weber',
        name: 'Markus Weber',
        email: `w${id}@example.de`,
        role: 'employee',
        ...overrides,
    } as User;
}

export function makeClient(overrides: Partial<Client> = {}): Client {
    const id = overrides.id ?? nextId();
    return {
        id,
        first_name: 'Klaus',
        last_name: 'Bergmann',
        name: 'Klaus Bergmann',
        company_name: 'Baeckerei Bergmann',
        email: null,
        phone: null,
        street: null,
        zip: null,
        city: null,
        ...overrides,
    } as Client;
}

/**
 * Build an appointment. `date` is a local `YYYY-MM-DD`, times are `HH:mm`.
 * Uses local-time ISO strings so the calendar's local parsing is exercised.
 */
export function makeAppointment(
    opts: {
        id?: number;
        date?: string;
        start?: string;
        end?: string;
        title?: string;
        workers?: User[];
        status?: Status | null;
        clients?: Client[];
    } = {},
): Appointment {
    const {
        id = nextId(),
        date = '2026-04-08',
        start = '09:00',
        end = '10:00',
        title = 'Routinekontrolle',
        workers = [],
        status = null,
        clients = [makeClient({ id: 800 + id })],
    } = opts;

    return {
        id,
        contract: { id: 900 + id, contract_number: `A-${900 + id}`, title, clients },
        start_at: `${date}T${start}:00`,
        end_at: `${date}T${end}:00`,
        status,
        workers,
        notes: null,
        checklist: null,
        recurrence_type: null,
        parent_id: null,
    } as unknown as Appointment;
}

/** Deterministic ids across a test file, so snapshots stay stable. */
export function resetIds(): void {
    seq = 0;
}
