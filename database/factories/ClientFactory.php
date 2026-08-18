<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'salutation' => 'Herr',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => fake()->company(),
            'street' => fake()->streetAddress(),
            'zip' => fake()->numerify('8####'),
            'city' => 'München',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
