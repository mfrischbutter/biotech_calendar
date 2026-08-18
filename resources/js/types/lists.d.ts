/**
 * The list screens and the generic DataTable that renders them.
 */

import type { AppointmentKind } from './domain';
import type { PipelineStage } from './dashboard';

export type SortDirection = 'asc' | 'desc';

export interface SortState {
    key: string;
    dir: SortDirection;
}

/**
 * One column of a DataTable. `label` is already translated by the page that
 * owns the table — the table itself never guesses at wording.
 */
export interface DataTableColumn {
    key: string;
    label: string;
    /** Sort key sent to the server. Omit to make the column unsortable. */
    sortKey?: string;
    /** Hidden until the reader turns it on in the column chooser. */
    hidden?: boolean;
    /** Identity column: never offered for hiding. */
    locked?: boolean;
    headerClass?: string;
    cellClass?: string;
}

export interface SavedView {
    key: string;
    label: string;
    count?: number;
}

export interface FilterChip {
    key: string;
    label: string;
}

export interface ListFilters {
    search: string | null;
    sort: string;
    dir: SortDirection;
    view?: string;
    role?: string | null;
}

export interface ClientListRow {
    id: number;
    name: string;
    first_name: string;
    last_name: string;
    salutation: string | null;
    company_name: string | null;
    billing_name: string | null;
    street: string | null;
    zip: string | null;
    city: string | null;
    phone: string | null;
    email: string | null;
    /** Contracts that still have work on them. */
    open_contracts: number;
    next_appointment: string | null;
}

export interface ContractProgress {
    done: number;
    total: number;
    percent: number;
}

export interface ContractListRow {
    id: number;
    contract_number: string;
    title: string;
    kind: AppointmentKind | null;
    description: string | null;
    street: string | null;
    zip: string | null;
    city: string | null;
    clients: { id: number; name: string; company_name: string | null }[];
    team: { id: number; name: string }[];
    stage: PipelineStage['stage'] | null;
    progress: ContractProgress;
}
