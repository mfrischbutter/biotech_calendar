<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { appointmentTypes } from '@/lib/appointment-types';
import { localToISO, isoToLocalParts } from '@/lib/use-calendar-drag';
import type { Appointment, AppointmentType } from '@/types';

const props = defineProps<{
    clients: { id: number; name: string }[];
    employees: { id: number; name: string }[];
    appointment?: Appointment;
    defaultDate?: string;
    defaultStartTime?: string;
    defaultEndTime?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEditing = computed(() => !!props.appointment);

// Parse appointment dates in local time (not raw UTC substring)
const initStart = props.appointment ? isoToLocalParts(props.appointment.start_at) : null;
const initEnd = props.appointment ? isoToLocalParts(props.appointment.end_at) : null;

const form = useForm({
    title: props.appointment?.title ?? '',
    client_id: props.appointment?.client?.id?.toString() ?? '',
    employee_id: props.appointment?.employee?.id?.toString() ?? '',
    start_date: initStart?.date ?? (props.defaultDate ?? ''),
    start_time: initStart?.time ?? (props.defaultStartTime ?? '09:00'),
    end_date: initEnd?.date ?? (props.defaultDate ?? ''),
    end_time: initEnd?.time ?? (props.defaultEndTime ?? '10:00'),
    type: props.appointment?.type ?? ('service' as AppointmentType),
    notes: props.appointment?.notes ?? '',
    is_recurring: false,
    recurrence_type: 'weekly',
    recurrence_interval: 1,
    recurrence_end: '',
});

// When the dialog overlay is clicked to dismiss, the overlay unmounts before
// the browser fires the "click" event.  That click then falls through to the
// calendar grid/appointment card underneath and immediately reopens the dialog.
// Fix: capture & discard the very next click at the document root.
function handlePointerDownOutside() {
    const stop = (e: Event) => { e.stopPropagation(); e.preventDefault(); };
    document.addEventListener('click', stop, { capture: true, once: true });
    document.addEventListener('mousedown', stop, { capture: true, once: true });
    // Safety cleanup in case the events never fire
    setTimeout(() => {
        document.removeEventListener('click', stop, { capture: true });
        document.removeEventListener('mousedown', stop, { capture: true });
    }, 300);
}

const clientPopoverOpen = ref(false);
const clientSearch = ref('');

const filteredClients = computed(() => {
    if (!clientSearch.value) return props.clients;
    const q = clientSearch.value.toLowerCase();
    return props.clients.filter((c) => c.name.toLowerCase().includes(q));
});

const selectedClientName = computed(() => {
    if (!form.client_id) return '';
    return props.clients.find((c) => c.id.toString() === form.client_id)?.name ?? '';
});

function selectClient(clientId: string) {
    form.client_id = clientId;
    clientPopoverOpen.value = false;
}

watch(open, (value) => {
    if (value && !isEditing.value) {
        // Re-populate defaults when dialog opens for creation
        form.start_date = props.defaultDate ?? '';
        form.start_time = props.defaultStartTime ?? '09:00';
        form.end_date = props.defaultDate ?? '';
        form.end_time = props.defaultEndTime ?? '10:00';
    }
    if (!value) {
        form.clearErrors();
        if (!isEditing.value) {
            form.reset();
        }
    }
});

const recurrenceLabels: Record<string, string> = {
    weekly: 'Wöchentlich',
    biweekly: 'Alle 2 Wochen',
    monthly: 'Monatlich',
    custom: 'Benutzerdefiniert',
};

function submit() {
    const payload: Record<string, unknown> = {
        title: form.title,
        client_id: form.client_id ? parseInt(form.client_id) : null,
        employee_id: form.employee_id ? parseInt(form.employee_id) : null,
        start_at: localToISO(form.start_date, form.start_time),
        end_at: localToISO(form.end_date, form.end_time),
        type: form.type,
        notes: form.notes || null,
    };

    if (form.is_recurring && !isEditing.value) {
        payload.recurrence_type = form.recurrence_type;
        payload.recurrence_interval = form.recurrence_type === 'custom' ? form.recurrence_interval : null;
        payload.recurrence_end = form.recurrence_end;
    }

    if (isEditing.value) {
        form.transform(() => payload).put(route('appointments.update', props.appointment!.id), {
            preserveScroll: true,
            onSuccess: () => { open.value = false; },
        });
    } else {
        form.transform(() => payload).post(route('appointments.store'), {
            preserveScroll: true,
            onSuccess: () => { open.value = false; },
        });
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[520px]" @pointer-down-outside="handlePointerDownOutside">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Termin bearbeiten' : 'Neuer Termin' }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Termindetails aktualisieren.' : 'Neuen Termin anlegen.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                <div class="space-y-2">
                    <Label for="appt-title">Titel *</Label>
                    <Input
                        id="appt-title"
                        v-model="form.title"
                        type="text"
                        placeholder="Terminbezeichnung"
                        required
                    />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>

                <!-- Client combobox -->
                <div class="space-y-2">
                    <Label>Kunde</Label>
                    <Popover v-model:open="clientPopoverOpen">
                        <PopoverTrigger as-child>
                            <Button variant="outline" role="combobox" class="w-full justify-between font-normal">
                                {{ selectedClientName || 'Kunde auswählen...' }}
                                <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-4 w-4 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></svg>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[--reka-popover-trigger-width] p-0">
                            <Command>
                                <CommandInput v-model="clientSearch" placeholder="Kunde suchen..." />
                                <CommandList>
                                    <CommandEmpty>Kein Kunde gefunden.</CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem value="" @select="selectClient('')">
                                            <span class="text-muted-foreground">Keiner</span>
                                        </CommandItem>
                                        <CommandItem
                                            v-for="client in filteredClients"
                                            :key="client.id"
                                            :value="client.name"
                                            @select="selectClient(client.id.toString())"
                                        >
                                            {{ client.name }}
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                <!-- Employee -->
                <div class="space-y-2">
                    <Label>Mitarbeiter</Label>
                    <Select v-model="form.employee_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Mitarbeiter auswählen..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Keiner</SelectItem>
                            <SelectItem
                                v-for="emp in employees"
                                :key="emp.id"
                                :value="emp.id.toString()"
                            >
                                {{ emp.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Date/Time -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="appt-start-date">Startdatum *</Label>
                        <Input id="appt-start-date" v-model="form.start_date" type="date" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="appt-start-time">Startzeit *</Label>
                        <Input id="appt-start-time" v-model="form.start_time" type="time" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="appt-end-date">Enddatum *</Label>
                        <Input id="appt-end-date" v-model="form.end_date" type="date" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="appt-end-time">Endzeit *</Label>
                        <Input id="appt-end-time" v-model="form.end_time" type="time" required />
                    </div>
                </div>
                <p v-if="(form.errors as Record<string, string>).start_at" class="text-sm text-destructive">{{ (form.errors as Record<string, string>).start_at }}</p>
                <p v-if="(form.errors as Record<string, string>).end_at" class="text-sm text-destructive">{{ (form.errors as Record<string, string>).end_at }}</p>

                <!-- Type -->
                <div class="space-y-2">
                    <Label>Typ *</Label>
                    <Select v-model="form.type">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(config, key) in appointmentTypes"
                                :key="key"
                                :value="key"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="config.dotColor" />
                                    {{ config.label }}
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Recurrence (only for create) -->
                <div v-if="!isEditing" class="space-y-3">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <Checkbox
                            :checked="form.is_recurring"
                            @update:checked="(val: boolean) => form.is_recurring = val"
                        />
                        Serientermin
                    </label>

                    <div v-if="form.is_recurring" class="space-y-3 rounded-md border p-3">
                        <div class="space-y-2">
                            <Label>Intervall</Label>
                            <Select v-model="form.recurrence_type">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, key) in recurrenceLabels"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="form.recurrence_type === 'custom'" class="space-y-2">
                            <Label for="appt-interval">Alle X Wochen</Label>
                            <Input
                                id="appt-interval"
                                v-model.number="form.recurrence_interval"
                                type="number"
                                min="1"
                                max="52"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="appt-recurrence-end">Enddatum Serie *</Label>
                            <Input id="appt-recurrence-end" v-model="form.recurrence_end" type="date" required />
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="appt-notes">Notizen</Label>
                    <Textarea
                        id="appt-notes"
                        v-model="form.notes"
                        placeholder="Zusätzliche Informationen..."
                        rows="3"
                    />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Speichern...' : (isEditing ? 'Aktualisieren' : 'Erstellen') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
