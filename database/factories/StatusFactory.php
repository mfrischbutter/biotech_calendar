<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Status>
 */
class StatusFactory extends Factory
{
    protected $model = Status::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Erste Maßnahme',
            'color' => '#F59E0B',
            'stage' => Status::STAGE_ACTIVE,
            'sort_order' => 1,
        ];
    }

    public function stage(string $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }

    public function readyToInvoice(): static
    {
        return $this->state(fn () => [
            'name' => 'Für Fakturierung bereit',
            'color' => '#22C55E',
            'stage' => Status::STAGE_READY_TO_INVOICE,
            'sort_order' => 5,
        ]);
    }
}
