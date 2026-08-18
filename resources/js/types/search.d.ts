/**
 * Global search: what the omnibox asks for and what comes back.
 */

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
