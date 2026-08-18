<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'contract_number' => 'A-'.fake()->unique()->numberBetween(1000, 9999),
            'title' => fake()->randomElement([
                'Routinekontrolle', 'Akutbehandlung Schaben', 'Nachkontrolle', 'Erstbegehung',
            ]),
            'kind' => 'kundentermin',
        ];
    }
}
