<?php

namespace Database\Seeders;

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
        User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@biotech.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        User::factory()->create([
            'name' => 'Employee One',
            'email' => 'employee1@biotech.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        User::factory()->create([
            'name' => 'Employee Two',
            'email' => 'employee2@biotech.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);
    }
}
