<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function createContract(User $user): Contract
    {
        return Contract::create([
            'company_id' => $user->company_id,
            'contract_number' => 'C-'.uniqid(),
            'title' => 'Test',
            'kind' => 'kundentermin',
        ]);
    }

    public function test_weekly_series_includes_occurrence_on_end_date(): void
    {
        $user = $this->createOwner();
        $contract = $this->createContract($user);

        $resp = $this->actingAs($user)->post('/appointments', [
            'contract_id' => $contract->id,
            'start_at' => '2026-05-08T10:00:00',
            'end_at' => '2026-05-08T11:00:00',
            'recurrence_type' => 'weekly',
            'recurrence_end' => '2026-05-22',
        ]);

        $resp->assertSessionHasNoErrors();
        // Parent (May 8) + occurrences on May 15 and May 22 = 3 total
        $this->assertSame(3, Appointment::count());
        $this->assertSame(2, Appointment::whereNotNull('parent_id')->count());
        $this->assertTrue(
            Appointment::where('start_at', '2026-05-22 10:00:00')->exists(),
            'Occurrence on the recurrence_end date must be created',
        );
    }

    public function test_biweekly_series_generates_correct_occurrences(): void
    {
        $user = $this->createOwner();
        $contract = $this->createContract($user);

        $this->actingAs($user)->post('/appointments', [
            'contract_id' => $contract->id,
            'start_at' => '2026-05-01T09:00:00',
            'end_at' => '2026-05-01T10:00:00',
            'recurrence_type' => 'biweekly',
            'recurrence_end' => '2026-05-29',
        ])->assertSessionHasNoErrors();

        // May 1 (parent), May 15, May 29 = 3
        $this->assertSame(3, Appointment::count());
        $this->assertTrue(Appointment::where('start_at', '2026-05-29 09:00:00')->exists());
    }

    public function test_custom_interval_series_requires_interval(): void
    {
        $user = $this->createOwner();
        $contract = $this->createContract($user);

        $this->actingAs($user)->post('/appointments', [
            'contract_id' => $contract->id,
            'start_at' => '2026-05-01T09:00:00',
            'end_at' => '2026-05-01T10:00:00',
            'recurrence_type' => 'custom',
            'recurrence_end' => '2026-06-30',
        ])->assertSessionHasErrors('recurrence_interval');

        $this->actingAs($user)->post('/appointments', [
            'contract_id' => $contract->id,
            'start_at' => '2026-05-01T09:00:00',
            'end_at' => '2026-05-01T10:00:00',
            'recurrence_type' => 'custom',
            'recurrence_interval' => 3,
            'recurrence_end' => '2026-06-30',
        ])->assertSessionHasNoErrors();

        // May 1 (parent), May 22, Jun 12 = 3
        $this->assertSame(3, Appointment::count());
    }

    public function test_delete_series_removes_parent_and_occurrences(): void
    {
        $user = $this->createOwner();
        $contract = $this->createContract($user);

        $this->actingAs($user)->post('/appointments', [
            'contract_id' => $contract->id,
            'start_at' => '2026-05-08T10:00:00',
            'end_at' => '2026-05-08T11:00:00',
            'recurrence_type' => 'weekly',
            'recurrence_end' => '2026-05-22',
        ]);

        $this->assertSame(3, Appointment::count());

        $parent = Appointment::whereNull('parent_id')->first();
        $this->actingAs($user)->delete("/appointments/{$parent->id}", [
            'delete_series' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Appointment::count());
    }

    public function test_delete_future_keeps_earlier_occurrences(): void
    {
        $user = $this->createOwner();
        $contract = $this->createContract($user);

        $this->actingAs($user)->post('/appointments', [
            'contract_id' => $contract->id,
            'start_at' => '2026-05-08T10:00:00',
            'end_at' => '2026-05-08T11:00:00',
            'recurrence_type' => 'weekly',
            'recurrence_end' => '2026-05-29',
        ]);

        // May 8 (parent), May 15, May 22, May 29 = 4 total
        $this->assertSame(4, Appointment::count());

        $may22 = Appointment::where('start_at', '2026-05-22 10:00:00')->first();
        $this->actingAs($user)->delete("/appointments/{$may22->id}", [
            'delete_future' => true,
        ])->assertSessionHasNoErrors();

        // Keeps May 8 (parent) and May 15 only
        $this->assertSame(2, Appointment::count());
        $this->assertTrue(Appointment::where('start_at', '2026-05-08 10:00:00')->exists());
        $this->assertTrue(Appointment::where('start_at', '2026-05-15 10:00:00')->exists());
    }
}
