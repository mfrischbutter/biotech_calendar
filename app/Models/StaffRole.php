<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named bundle of permissions, so onboarding a technician is one choice
 * instead of twenty-two switches.
 */
class StaffRole extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'slug',
        'name',
        'description',
        'permissions',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public const SLUG_TECHNICIAN = 'techniker';

    public const SLUG_LEAD = 'einsatzleiter';

    public const SLUG_OFFICE = 'buero';

    public const SLUG_ADMIN = 'verwaltung';

    /**
     * The presets every company starts with. Companies may edit them afterwards.
     *
     * @return array<int, array{slug: string, name: string, description: string, permissions: array<int, string>, sort_order: int}>
     */
    public static function presets(): array
    {
        return [
            [
                'slug' => self::SLUG_TECHNICIAN,
                'name' => 'Techniker',
                'description' => 'Sieht die eigenen Termine und die Kundendaten, die dafür nötig sind.',
                'permissions' => [
                    'dashboard.view',
                    'appointments.view',
                    'appointments.edit',
                    'clients.view',
                    'contracts.view',
                    'treatments.view',
                    'treatments.create',
                    'treatments.edit',
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => self::SLUG_LEAD,
                'name' => 'Einsatzleiter',
                'description' => 'Plant Termine, teilt das Team ein und pflegt Aufträge.',
                'permissions' => [
                    'dashboard.view',
                    'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                    'clients.view', 'clients.create', 'clients.edit',
                    'contracts.view', 'contracts.create', 'contracts.edit',
                    'treatments.view', 'treatments.create', 'treatments.edit',
                    'reports.view',
                ],
                'sort_order' => 2,
            ],
            [
                'slug' => self::SLUG_OFFICE,
                'name' => 'Büro',
                'description' => 'Pflegt Kunden und Aufträge und kümmert sich um die Abrechnung.',
                'permissions' => [
                    'dashboard.view',
                    'appointments.view', 'appointments.create', 'appointments.edit',
                    'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                    'contracts.view', 'contracts.create', 'contracts.edit', 'contracts.delete',
                    'invoices.view', 'invoices.create',
                    'reports.view', 'reports.export',
                ],
                'sort_order' => 3,
            ],
            [
                'slug' => self::SLUG_ADMIN,
                'name' => 'Verwaltung',
                'description' => 'Vollzugriff inklusive Einstellungen.',
                'permissions' => array_keys(Permission::ALL),
                'sort_order' => 4,
            ],
        ];
    }

    /** Create the preset roles for a company that has none yet. */
    public static function seedForCompany(int $companyId): void
    {
        foreach (self::presets() as $preset) {
            self::firstOrCreate(
                ['company_id' => $companyId, 'slug' => $preset['slug']],
                [...$preset, 'company_id' => $companyId, 'is_system' => true],
            );
        }
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * True when the user's granted permissions no longer match this role,
     * i.e. someone hand-tuned them.
     *
     * @param  array<int, string>  $granted
     */
    public function matches(array $granted): bool
    {
        $a = $this->permissions ?? [];
        sort($a);
        sort($granted);

        return $a === $granted;
    }
}
