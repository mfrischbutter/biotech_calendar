<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Status;
use App\Models\User;

class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        $clientName = $appointment->client?->name;
        $description = "Termin \"{$appointment->title}\" erstellt";
        if ($clientName) {
            $description .= " ({$clientName})";
        }

        ActivityLog::create([
            'company_id' => $appointment->company_id,
            'appointment_id' => $appointment->id,
            'user_id' => auth()->id(),
            'action' => 'created',
            'description' => $description,
        ]);
    }

    public function updated(Appointment $appointment): void
    {
        $dirty = $appointment->getDirty();
        unset($dirty['updated_at']);

        if (empty($dirty)) {
            return;
        }

        $changes = [];
        foreach ($dirty as $field => $newValue) {
            $oldValue = $appointment->getOriginal($field);

            if ($field === 'client_id') {
                $oldName = $oldValue ? Client::find($oldValue)?->name : null;
                $newName = $newValue ? Client::find($newValue)?->name : null;
                $changes['client'] = ['old' => $oldName, 'new' => $newName];
            } elseif ($field === 'employee_id') {
                $oldName = $oldValue ? User::find($oldValue)?->name : null;
                $newName = $newValue ? User::find($newValue)?->name : null;
                $changes['employee'] = ['old' => $oldName, 'new' => $newName];
            } elseif ($field === 'status_id') {
                $oldName = $oldValue ? Status::find($oldValue)?->name : null;
                $newName = $newValue ? Status::find($newValue)?->name : null;
                $changes['status'] = ['old' => $oldName, 'new' => $newName];
            } else {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        $description = "Termin \"{$appointment->title}\" aktualisiert";

        ActivityLog::create([
            'company_id' => $appointment->company_id,
            'appointment_id' => $appointment->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'changes' => $changes,
            'description' => $description,
        ]);
    }

    public function deleting(Appointment $appointment): void
    {
        ActivityLog::create([
            'company_id' => $appointment->company_id,
            'appointment_id' => $appointment->id,
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'description' => "Termin \"{$appointment->title}\" gelöscht",
        ]);
    }
}
