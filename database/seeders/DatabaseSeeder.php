<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Biotech GmbH',
        ]);

        // Default calendar settings
        Setting::create(['company_id' => $company->id, 'key' => 'show_weekends', 'value' => 'false']);
        Setting::create(['company_id' => $company->id, 'key' => 'start_hour', 'value' => '8']);
        Setting::create(['company_id' => $company->id, 'key' => 'end_hour', 'value' => '18']);

        // Default tags
        $defaultTags = [
            ['name' => 'Servicetermin', 'color' => '#3b82f6'],
            ['name' => 'Erstbegehung', 'color' => '#f97316'],
            ['name' => 'Abgeschlossen', 'color' => '#22c55e'],
            ['name' => 'Nachkontrolle', 'color' => '#a855f7'],
            ['name' => 'Beratung', 'color' => '#ec4899'],
            ['name' => 'Dokumentation', 'color' => '#6b7280'],
        ];

        foreach ($defaultTags as $i => $tag) {
            Tag::create([
                'company_id' => $company->id,
                'name' => $tag['name'],
                'color' => $tag['color'],
                'sort_order' => $i,
            ]);
        }

        User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@biotech.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'company_id' => $company->id,
        ]);

        User::factory()->create([
            'name' => 'Employee One',
            'email' => 'employee1@biotech.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'company_id' => $company->id,
        ]);

        User::factory()->create([
            'name' => 'Employee Two',
            'email' => 'employee2@biotech.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
            'company_id' => $company->id,
        ]);
    }
}
