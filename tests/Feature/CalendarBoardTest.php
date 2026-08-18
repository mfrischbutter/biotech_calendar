<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

/**
 * The calendar board: which appointments a view returns, how the filter rail
 * narrows them, and what the toolbar and team headers count.
 */
class CalendarBoardTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    private User $anna;

    private User $max;

    private Status $open;

    private Status $active;

    private Contract $contract;

    /** @var array<string, Appointment> */
    private array $appointments = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday of the week the fixtures live in.
        Carbon::setTestNow('2026-04-08 12:00:00');

        $this->company = Company::factory()->create(['name' => 'Wilhelmsen BioTec']);
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
            'first_name' => 'Bjoern',
            'last_name' => 'Wilhelmsen',
        ]);
        $this->anna = $this->employee('Anna', 'Berg');
        $this->max = $this->employee('Max', 'Kern');

        $this->open = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Offen',
            'color' => '#3b82f6',
            'stage' => Status::STAGE_UNCONFIRMED,
            'sort_order' => 1,
        ]);
        $this->active = Status::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'In Arbeit',
            'color' => '#22c55e',
            'stage' => Status::STAGE_ACTIVE,
            'sort_order' => 2,
        ]);

        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->owner->id,
            'first_name' => 'Klaus',
            'last_name' => 'Bergmann',
            'company_name' => 'Baeckerei Bergmann',
        ]);

        $this->contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'contract_number' => 'A-1000',
            'title' => 'Routinekontrolle',
            'kind' => 'kundentermin',
            'street' => 'Hauptstr. 1',
            'zip' => '80331',
            'city' => 'Muenchen',
        ]);
        $this->contract->clients()->attach($client->id);

        // Anna is booked twice at once on Wednesday morning — the case the app
        // used to allow in silence.
        $this->appointments['annaEarly'] = $this->appointment('2026-04-08 09:00:00', 60, $this->open, [$this->anna]);
        $this->appointments['annaClash'] = $this->appointment('2026-04-08 09:30:00', 60, $this->active, [$this->anna]);
        $this->appointments['max'] = $this->appointment('2026-04-08 11:00:00', 60, $this->active, [$this->max]);
        $this->appointments['orphan'] = $this->appointment('2026-04-08 14:00:00', 60, null, []);
        $this->appointments['maxThursday'] = $this->appointment('2026-04-09 10:00:00', 120, $this->active, [$this->max]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(string $first, string $last): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
            'first_name' => $first,
            'last_name' => $last,
        ]);
        $user->syncPermissions(['appointments.view']);

        return $user;
    }

    /**
     * @param  array<int, User>  $workers
     */
    private function appointment(string $start, int $minutes, ?Status $status, array $workers): Appointment
    {
        $appointment = Appointment::factory()->at($start, $minutes)->create([
            'company_id' => $this->company->id,
            'contract_id' => $this->contract->id,
            'created_by' => $this->owner->id,
            'status_id' => $status?->id,
        ]);
        $appointment->workers()->sync(collect($workers)->pluck('id')->all());

        return $appointment;
    }

    private function board(array $query = []): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('calendar.index', $query));
    }

    /**
     * @return array<string, mixed>
     */
    private function props(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    /**
     * @return array<int, int>
     */
    private function idsOf(TestResponse $response): array
    {
        return collect($this->props($response)['appointments'])->pluck('id')->sort()->values()->all();
    }

    public function test_it_defaults_to_the_team_week(): void
    {
        $response = $this->board();

        $response->assertOk();
        $this->assertSame('team-week', $this->props($response)['view']);
        $this->assertSame('2026-04-08', $this->props($response)['currentDate']);
    }

    public function test_it_requires_the_appointments_view_permission(): void
    {
        $blocked = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $this->actingAs($blocked)->get(route('calendar.index'))->assertForbidden();
    }

    public function test_it_rejects_an_unknown_view(): void
    {
        $this->actingAs($this->owner)
            ->get(route('calendar.index', ['view' => 'gantt']))
            ->assertSessionHasErrors('view');
    }

    public function test_it_rejects_a_filter_on_an_unknown_employee(): void
    {
        $this->actingAs($this->owner)
            ->get(route('calendar.index', ['employees' => [999999]]))
            ->assertSessionHasErrors('employees.0');
    }

    public function test_it_filters_to_one_employee(): void
    {
        $response = $this->board(['employees' => [$this->anna->id]]);

        $this->assertSame(
            collect([$this->appointments['annaEarly'], $this->appointments['annaClash']])->pluck('id')->sort()->values()->all(),
            $this->idsOf($response),
        );
        $this->assertSame([$this->anna->id], $this->props($response)['filters']['employees']);
    }

    public function test_employee_and_unassigned_filters_are_a_union_not_an_intersection(): void
    {
        $response = $this->board(['employees' => [$this->max->id], 'unassigned' => 1]);

        $this->assertSame(
            collect([$this->appointments['max'], $this->appointments['orphan'], $this->appointments['maxThursday']])
                ->pluck('id')->sort()->values()->all(),
            $this->idsOf($response),
        );
    }

    public function test_it_filters_to_work_nobody_owns(): void
    {
        $response = $this->board(['unassigned' => 1]);

        $this->assertSame([$this->appointments['orphan']->id], $this->idsOf($response));
    }

    public function test_it_filters_by_status(): void
    {
        $response = $this->board(['statuses' => [$this->open->id]]);

        $this->assertSame([$this->appointments['annaEarly']->id], $this->idsOf($response));
    }

    public function test_it_combines_status_and_employee_filters(): void
    {
        $response = $this->board([
            'employees' => [$this->anna->id],
            'statuses' => [$this->active->id],
        ]);

        $this->assertSame([$this->appointments['annaClash']->id], $this->idsOf($response));
    }

    public function test_it_maps_double_bookings_in_both_directions(): void
    {
        $conflicts = (array) $this->props($this->board())['conflicts'];
        $early = $this->appointments['annaEarly']->id;
        $clash = $this->appointments['annaClash']->id;

        $this->assertEqualsCanonicalizing([$early, $clash], array_map('intval', array_keys($conflicts)));
        $this->assertSame([$clash], $conflicts[$early]);
        $this->assertSame([$early], $conflicts[$clash]);
    }

    public function test_back_to_back_appointments_do_not_conflict(): void
    {
        $this->appointment('2026-04-10 08:00:00', 60, $this->active, [$this->max]);
        $this->appointment('2026-04-10 09:00:00', 60, $this->active, [$this->max]);

        $conflicts = (array) $this->props($this->board())['conflicts'];

        $this->assertCount(2, $conflicts, 'Only Annas overlapping pair should be flagged.');
    }

    public function test_the_conflicts_filter_narrows_to_the_clashing_appointments(): void
    {
        $response = $this->board(['conflicts' => 1]);

        $this->assertSame(
            collect([$this->appointments['annaEarly'], $this->appointments['annaClash']])->pluck('id')->sort()->values()->all(),
            $this->idsOf($response),
        );
        $this->assertSame(2, $this->props($response)['conflictCount']);
    }

    public function test_the_conflict_count_ignores_the_other_filters(): void
    {
        // Hiding a status must not make a double-booking disappear from the toolbar.
        $response = $this->board(['statuses' => [$this->open->id]]);

        $this->assertSame([$this->appointments['annaEarly']->id], $this->idsOf($response));
        $this->assertSame(2, $this->props($response)['conflictCount']);
    }

    public function test_totals_count_an_appointment_once_per_technician(): void
    {
        $shared = $this->appointment('2026-04-10 08:00:00', 60, $this->active, [$this->anna, $this->max]);

        $perEmployee = collect($this->props($this->board())['totals']['perEmployee'])->keyBy('id');

        $this->assertSame(3, $perEmployee[$this->anna->id]['appointments']);
        $this->assertSame(180, $perEmployee[$this->anna->id]['minutes']);
        $this->assertSame(3, $perEmployee[$this->max->id]['appointments']);
        $this->assertSame(240, $perEmployee[$this->max->id]['minutes']);
        $this->assertNotNull($shared->id);
    }

    public function test_unowned_work_gets_its_own_totals_row(): void
    {
        $unassigned = collect($this->props($this->board())['totals']['perEmployee'])
            ->firstWhere('id', null);

        $this->assertSame(['id' => null, 'appointments' => 1, 'minutes' => 60], $unassigned);
    }

    public function test_day_totals_cover_the_whole_visible_week(): void
    {
        $perDay = collect($this->props($this->board())['totals']['perDay'])->keyBy('date');

        $this->assertCount(7, $perDay, 'Monday to Sunday, including the empty days.');
        $this->assertSame(['date' => '2026-04-08', 'appointments' => 4, 'minutes' => 240], $perDay['2026-04-08']);
        $this->assertSame(['date' => '2026-04-09', 'appointments' => 1, 'minutes' => 120], $perDay['2026-04-09']);
        $this->assertSame(0, $perDay['2026-04-06']['appointments']);
    }

    public function test_totals_follow_the_filters(): void
    {
        $perDay = collect($this->props($this->board(['employees' => [$this->max->id]]))['totals']['perDay'])->keyBy('date');

        $this->assertSame(1, $perDay['2026-04-08']['appointments']);
        $this->assertSame(60, $perDay['2026-04-08']['minutes']);
    }

    public function test_the_day_view_narrows_the_range(): void
    {
        $response = $this->board(['view' => 'day', 'date' => '2026-04-09']);

        $this->assertSame([$this->appointments['maxThursday']->id], $this->idsOf($response));
        $this->assertCount(1, $this->props($response)['totals']['perDay']);
    }

    public function test_opening_an_appointment_jumps_to_its_week(): void
    {
        $far = $this->appointment('2026-05-20 09:00:00', 60, $this->active, [$this->max]);

        $props = $this->props($this->board(['appointment' => $far->id]));

        $this->assertSame('2026-05-20', $props['currentDate']);
        $this->assertSame($far->id, $props['openAppointmentId']);
    }

    public function test_the_calendar_payload_shape_is_stable(): void
    {
        $response = $this->board();

        $this->assertMatchesJsonSnapshot(
            $this->inertiaProps($response, [
                'appointments', 'contracts', 'employees', 'statuses', 'clients',
                'currentDate', 'view', 'showWeekends', 'startHour', 'endHour',
                'openAppointmentId', 'filters', 'conflictCount', 'totals',
            ]),
            'calendar-board',
        );
    }
}
