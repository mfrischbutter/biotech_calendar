import type { AppointmentType } from '@/types';

export interface AppointmentTypeConfig {
    label: string;
    color: string;
    bgColor: string;
    borderColor: string;
    dotColor: string;
}

export const appointmentTypes: Record<AppointmentType, AppointmentTypeConfig> = {
    service: {
        label: 'Servicetermin',
        color: 'text-blue-700',
        bgColor: 'bg-blue-50',
        borderColor: 'border-blue-500',
        dotColor: 'bg-blue-500',
    },
    initial_visit: {
        label: 'Erstbegehung',
        color: 'text-orange-700',
        bgColor: 'bg-orange-50',
        borderColor: 'border-orange-500',
        dotColor: 'bg-orange-500',
    },
    completed: {
        label: 'Abgeschlossen',
        color: 'text-green-700',
        bgColor: 'bg-green-50',
        borderColor: 'border-green-500',
        dotColor: 'bg-green-500',
    },
    follow_up: {
        label: 'Nachkontrolle',
        color: 'text-purple-700',
        bgColor: 'bg-purple-50',
        borderColor: 'border-purple-500',
        dotColor: 'bg-purple-500',
    },
    consultation: {
        label: 'Beratung',
        color: 'text-pink-700',
        bgColor: 'bg-pink-50',
        borderColor: 'border-pink-500',
        dotColor: 'bg-pink-500',
    },
    documentation: {
        label: 'Dokumentation',
        color: 'text-gray-700',
        bgColor: 'bg-gray-50',
        borderColor: 'border-gray-500',
        dotColor: 'bg-gray-500',
    },
};
