<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'permission'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public const ALL = [
        // Dashboard access
        'dashboard.view' => 'Access web dashboard',

        // Client management
        'clients.view' => 'View clients',
        'clients.create' => 'Create clients',
        'clients.edit' => 'Edit clients',
        'clients.delete' => 'Delete clients',

        // Contracts
        'contracts.view' => 'View contracts',
        'contracts.create' => 'Create contracts',
        'contracts.edit' => 'Edit contracts',
        'contracts.delete' => 'Delete contracts',

        // Appointments
        'appointments.view' => 'View appointments',
        'appointments.create' => 'Create appointments',
        'appointments.edit' => 'Edit appointments',
        'appointments.delete' => 'Delete appointments',

        // Treatments & protocols
        'treatments.view' => 'View treatments',
        'treatments.create' => 'Create treatments',
        'treatments.edit' => 'Edit treatments',

        // Invoicing & payments
        'invoices.view' => 'View invoices',
        'invoices.create' => 'Create invoices',

        // Reports
        'reports.view' => 'View reports',
        'reports.export' => 'Export reports',

        // Settings
        'settings.view' => 'View settings',
        'settings.edit' => 'Edit settings',
    ];
}
