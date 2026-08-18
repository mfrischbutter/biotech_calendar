<?php

namespace Database\Factories;

use App\Models\ChecklistTemplate;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistTemplate>
 */
class ChecklistTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Routinekontrolle',
            'kind' => 'kundentermin',
            'items' => ['Köderboxen prüfen', 'Befall dokumentieren', 'Kunde informieren'],
            'sort_order' => 0,
        ];
    }
}
