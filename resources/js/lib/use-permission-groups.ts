import { computed } from 'vue';

export const permissionGroupLabels: Record<string, string> = {
    dashboard: 'Dashboard Access',
    clients: 'Clients',
    contracts: 'Contracts Access',
    appointments: 'Appointments',
    treatments: 'Treatments & Protocols',
    invoices: 'Invoicing & Payments',
    reports: 'Reports',
};

export function usePermissionGroups(availablePermissions: () => Record<string, string>) {
    const permissionGroups = computed(() => {
        const groups: Record<string, { key: string; label: string }[]> = {};
        for (const [key, label] of Object.entries(availablePermissions())) {
            const group = key.split('.')[0];
            if (!groups[group]) groups[group] = [];
            groups[group].push({ key, label });
        }
        return groups;
    });

    return { permissionGroups, permissionGroupLabels };
}
