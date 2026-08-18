<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Editing a recurring appointment is a two-way choice, and getting it wrong is
 * data loss. These tests pin both directions down hard.
 */
class AppointmentSeriesScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Contract $contract;

    private Appointment $parent;

    /** @var array<int, Appointment> */
    private array $children = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-08 12:00:00');

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
        ]);
        $this->contract = Contract::factory()->create(['company_id' => $this->company->id]);

        // A weekly series: 6, 13 and 20 April, each 09:00–10:00.
        $this->parent = $this->occurrence('2026-04-06 09:00:00', null, [
            'recurrence_type' => 'weekly',
            'recurrence_end' => '2026-04-20',
        ]);
        $this->children = [
            $this->occurrence('2026-04-13 09:00:00', $this->parent->id),
            $this->occurrence('2026-04-20 09:00:00', $this->parent->id),
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $extra */
    private function occurrence(string $start, ?int $parentId, array $extra = []): Appointment
    {
        return Appointment::factory()->create([
            'company_id' => $this->company->id,
            'contract_id' => $this->contract->id,
            'created_by' => $this->owner->id,
            'parent_id' => $parentId,
            'start_at' => $start,
            'end_at' => Carbon::parse($start)->addHour(),
            ...$extra,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function editMiddleOccurrence(array $payload)
    {
        return $this->actingAs($this->owner)
            ->put(route('appointments.update', $this->children[0]->id), [
                'contract_id' => $this->contract->id,
                'start_at' => '2026-04-13 09:00:00',
                'end_at' => '2026-04-13 10:00:00',
                ...$payload,
            ]);
    }

    public function test_editing_one_occurrence_leaves_its_siblings_untouched(): void
    {
        $this->editMiddleOccurrence([
            'scope' => 'single',
            'start_at' => '2026-04-13 14:00:00',
            'end_at' => '2026-04-13 15:30:00',
            'notes' => 'Nur dieser Termin',
        ])->assertRedirect();

        $edited = $this->children[0]->fresh();
        $this->assertSame('2026-04-13 14:00:00', $edited->start_at->toDateTimeString());
        $this->assertSame('2026-04-13 15:30:00', $edited->end_at->toDateTimeString());
        $this->assertSame('Nur dieser Termin', $edited->notes);

        $this->assertSame('2026-04-06 09:00:00', $this->parent->fresh()->start_at->toDateTimeString());
        $this->assertSame('2026-04-20 09:00:00', $this->children[1]->fresh()->start_at->toDateTimeString());
        $this->assertNull($this->parent->fresh()->notes);
        $this->assertNull($this->children[1]->fresh()->notes);
    }

    public function test_omitting_the_scope_defaults_to_this_occurrence_only(): void
    {
        $this->editMiddleOccurrence(['notes' => 'Ohne scope'])->assertRedirect();

        $this->assertSame('Ohne scope', $this->children[0]->fresh()->notes);
        $this->assertNull($this->parent->fresh()->notes);
        $this->assertNull($this->children[1]->fresh()->notes);
    }

    public function test_the_series_scope_moves_the_time_of_day_on_every_member(): void
    {
        $this->editMiddleOccurrence([
            'scope' => 'series',
            'start_at' => '2026-04-13 14:00:00',
            'end_at' => '2026-04-13 15:30:00',
        ])->assertRedirect();

        $starts = [
            $this->parent->fresh()->start_at->toDateTimeString(),
            $this->children[0]->fresh()->start_at->toDateTimeString(),
            $this->children[1]->fresh()->start_at->toDateTimeString(),
        ];

        // Each member keeps its own date and takes the new time and duration.
        $this->assertSame([
            '2026-04-06 14:00:00',
            '2026-04-13 14:00:00',
            '2026-04-20 14:00:00',
        ], $starts);

        $this->assertSame('2026-04-06 15:30:00', $this->parent->fresh()->end_at->toDateTimeString());
        $this->assertSame('2026-04-20 15:30:00', $this->children[1]->fresh()->end_at->toDateTimeString());
    }

    public function test_the_series_scope_shifts_every_member_by_the_days_the_user_moved(): void
    {
        $this->editMiddleOccurrence([
            'scope' => 'series',
            'start_at' => '2026-04-15 09:00:00',
            'end_at' => '2026-04-15 10:00:00',
        ])->assertRedirect();

        $this->assertSame('2026-04-08 09:00:00', $this->parent->fresh()->start_at->toDateTimeString());
        $this->assertSame('2026-04-15 09:00:00', $this->children[0]->fresh()->start_at->toDateTimeString());
        $this->assertSame('2026-04-22 09:00:00', $this->children[1]->fresh()->start_at->toDateTimeString());
    }

    public function test_the_series_scope_copies_notes_status_and_checklist_to_every_member(): void
    {
        $status = Status::factory()->create(['company_id' => $this->company->id]);

        $this->editMiddleOccurrence([
            'scope' => 'series',
            'status_id' => $status->id,
            'notes' => 'Für die ganze Serie',
            'checklist' => [['text' => 'Köderboxen prüfen', 'checked' => false]],
        ])->assertRedirect();

        foreach ([$this->parent, ...$this->children] as $member) {
            $fresh = $member->fresh();
            $this->assertSame('Für die ganze Serie', $fresh->notes);
            $this->assertSame($status->id, $fresh->status_id);
            $this->assertSame([['text' => 'Köderboxen prüfen', 'checked' => false]], $fresh->checklist);
        }
    }

    public function test_the_series_scope_assigns_the_workers_to_every_member(): void
    {
        $anna = User::factory()->create(['company_id' => $this->company->id]);

        $this->editMiddleOccurrence(['scope' => 'series', 'worker_ids' => [$anna->id]])
            ->assertRedirect();

        foreach ([$this->parent, ...$this->children] as $member) {
            $this->assertSame([$anna->id], $member->fresh()->workers()->pluck('users.id')->all());
        }
    }

    public function test_the_series_scope_never_touches_the_recurrence_rule(): void
    {
        $this->editMiddleOccurrence(['scope' => 'series', 'notes' => 'x'])->assertRedirect();

        $parent = $this->parent->fresh();
        $this->assertSame('weekly', $parent->recurrence_type);
        $this->assertSame('2026-04-20', $parent->recurrence_end->toDateString());
        $this->assertNull($this->children[0]->fresh()->recurrence_type);
    }

    public function test_the_series_scope_on_a_standalone_appointment_only_writes_that_one(): void
    {
        $solo = $this->occurrence('2026-04-09 09:00:00', null);

        $this->actingAs($this->owner)
            ->put(route('appointments.update', $solo->id), [
                'contract_id' => $this->contract->id,
                'start_at' => '2026-04-09 11:00:00',
                'end_at' => '2026-04-09 12:00:00',
                'scope' => 'series',
            ])->assertRedirect();

        $this->assertSame('2026-04-09 11:00:00', $solo->fresh()->start_at->toDateTimeString());
        $this->assertSame('2026-04-06 09:00:00', $this->parent->fresh()->start_at->toDateTimeString());
    }

    public function test_an_unknown_scope_is_rejected(): void
    {
        $this->editMiddleOccurrence(['scope' => 'everything'])
            ->assertSessionHasErrors('scope');

        $this->assertSame('2026-04-13 09:00:00', $this->children[0]->fresh()->start_at->toDateTimeString());
    }

    public function test_editing_a_series_requires_the_edit_permission(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($employee)
            ->put(route('appointments.update', $this->children[0]->id), [
                'contract_id' => $this->contract->id,
                'start_at' => '2026-04-13 14:00:00',
                'end_at' => '2026-04-13 15:00:00',
                'scope' => 'series',
            ])->assertForbidden();

        $this->assertSame('2026-04-13 09:00:00', $this->children[0]->fresh()->start_at->toDateTimeString());
    }
}
