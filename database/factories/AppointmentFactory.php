<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = now()->startOfHour()->addDay()->setHour(9);

        return [
            'company_id' => Company::factory(),
            'contract_id' => Contract::factory(),
            'created_by' => User::factory(),
            'start_at' => $start,
            'end_at' => (clone $start)->addHour(),
        ];
    }

    /** Place the appointment at a specific local time, e.g. at('2026-04-08 09:00', 90). */
    public function at(string $start, int $minutes = 60): static
    {
        return $this->state(fn () => [
            'start_at' => $start,
            'end_at' => Carbon::parse($start)->addMinutes($minutes),
        ]);
    }
}
