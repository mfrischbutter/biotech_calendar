<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The team board's density is a URL parameter, not a hidden local preference:
 * a link to a board has to open the same way for the colleague who receives it.
 */
class CalendarDensityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-08 12:00:00');

        $company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_OWNER,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function board(array $query = []): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('calendar.index', $query));
    }

    public function test_density_is_absent_until_the_url_asks_for_one(): void
    {
        $props = $this->board()->viewData('page')['props'];

        $this->assertNull($props['density']);
    }

    public function test_density_is_echoed_back_from_the_query_string(): void
    {
        foreach (['compact', 'detailed'] as $density) {
            $props = $this->board(['density' => $density])->viewData('page')['props'];

            $this->assertSame($density, $props['density']);
        }
    }

    public function test_an_unknown_density_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->get(route('calendar.index', ['density' => 'gigantic']))
            ->assertSessionHasErrors('density');
    }

    public function test_density_travels_alongside_the_filter_rail(): void
    {
        $props = $this->board(['density' => 'detailed', 'unassigned' => 1, 'view' => 'team-week'])
            ->viewData('page')['props'];

        $this->assertSame('detailed', $props['density']);
        $this->assertTrue($props['filters']['unassigned']);
        $this->assertSame('team-week', $props['view']);
    }
}
