<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MatchesJsonSnapshots;
use Tests\TestCase;

/**
 * Checklist templates: who may manage them, what they accept, and how the
 * appointment form and the settings screen are handed them.
 */
class ChecklistTemplateTest extends TestCase
{
    use MatchesJsonSnapshots, RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Routinekontrolle',
            'kind' => 'kundentermin',
            'items' => ['Köderboxen prüfen', 'Befall dokumentieren'],
            ...$overrides,
        ];
    }

    public function test_an_owner_can_create_a_template(): void
    {
        $this->actingAs($this->owner)
            ->post(route('checklist-templates.store'), $this->payload())
            ->assertRedirect();

        $template = ChecklistTemplate::firstOrFail();
        $this->assertSame('Routinekontrolle', $template->name);
        $this->assertSame('kundentermin', $template->kind);
        $this->assertSame(['Köderboxen prüfen', 'Befall dokumentieren'], $template->items);
        $this->assertSame($this->company->id, $template->company_id);
    }

    public function test_a_template_without_a_kind_applies_everywhere(): void
    {
        $this->actingAs($this->owner)
            ->post(route('checklist-templates.store'), $this->payload(['kind' => null]))
            ->assertRedirect();

        $this->assertNull(ChecklistTemplate::firstOrFail()->kind);
    }

    public function test_new_templates_are_appended_to_the_end_of_the_list(): void
    {
        ChecklistTemplate::factory()->create(['company_id' => $this->company->id, 'sort_order' => 7]);

        $this->actingAs($this->owner)
            ->post(route('checklist-templates.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(8, ChecklistTemplate::orderByDesc('sort_order')->first()->sort_order);
    }

    public function test_a_template_needs_a_name_and_at_least_one_item(): void
    {
        $this->actingAs($this->owner)
            ->post(route('checklist-templates.store'), $this->payload(['name' => '', 'items' => []]))
            ->assertSessionHasErrors(['name', 'items']);

        $this->assertSame(0, ChecklistTemplate::count());
    }

    public function test_an_unknown_service_type_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('checklist-templates.store'), $this->payload(['kind' => 'kaffeepause']))
            ->assertSessionHasErrors('kind');
    }

    public function test_a_template_may_not_hold_more_than_fifty_items(): void
    {
        $this->actingAs($this->owner)
            ->post(route('checklist-templates.store'), $this->payload([
                'items' => array_fill(0, 51, 'Punkt'),
            ]))
            ->assertSessionHasErrors('items');
    }

    public function test_an_owner_can_rewrite_a_template(): void
    {
        $template = ChecklistTemplate::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->owner)
            ->put(route('checklist-templates.update', $template->id), $this->payload([
                'name' => 'Nachkontrolle',
                'kind' => 'ohne_termin',
                'items' => ['Fallen leeren'],
            ]))
            ->assertRedirect();

        $fresh = $template->fresh();
        $this->assertSame('Nachkontrolle', $fresh->name);
        $this->assertSame('ohne_termin', $fresh->kind);
        $this->assertSame(['Fallen leeren'], $fresh->items);
    }

    public function test_an_owner_can_delete_a_template(): void
    {
        $template = ChecklistTemplate::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->owner)
            ->delete(route('checklist-templates.destroy', $template->id))
            ->assertRedirect();

        $this->assertSame(0, ChecklistTemplate::count());
    }

    public function test_an_employee_without_settings_edit_may_not_manage_templates(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        $template = ChecklistTemplate::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($employee)->post(route('checklist-templates.store'), $this->payload())
            ->assertForbidden();
        $this->actingAs($employee)->put(route('checklist-templates.update', $template->id), $this->payload())
            ->assertForbidden();
        $this->actingAs($employee)->delete(route('checklist-templates.destroy', $template->id))
            ->assertForbidden();

        $this->assertSame(1, ChecklistTemplate::count());
    }

    public function test_an_employee_with_settings_edit_may_manage_templates(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        Permission::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'permission' => 'settings.edit',
        ]);

        $this->actingAs($employee)
            ->post(route('checklist-templates.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(1, ChecklistTemplate::count());
    }

    public function test_templates_from_another_company_are_out_of_reach(): void
    {
        $other = Company::factory()->create();
        $foreign = ChecklistTemplate::factory()->create(['company_id' => $other->id]);

        $this->actingAs($this->owner)
            ->put(route('checklist-templates.update', $foreign->id), $this->payload())
            ->assertNotFound();

        $this->assertSame('Routinekontrolle', $foreign->fresh()->name);
    }

    public function test_the_settings_screen_and_the_calendar_both_receive_the_templates(): void
    {
        ChecklistTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Erstbegehung',
            'kind' => null,
            'items' => ['Objekt begehen'],
            'sort_order' => 0,
        ]);

        $settings = $this->actingAs($this->owner)->get(route('settings.index'));
        $settings->assertOk();

        $calendar = $this->actingAs($this->owner)->get(route('calendar.index'));
        $calendar->assertOk();

        $this->assertMatchesJsonSnapshot(
            [
                'settings' => $this->inertiaProps($settings, ['checklistTemplates']),
                'calendar' => $this->inertiaProps($calendar, ['checklistTemplates']),
            ],
            'checklist-templates',
        );
    }

    public function test_a_template_renders_as_unchecked_checklist_items(): void
    {
        $template = ChecklistTemplate::factory()->create([
            'company_id' => $this->company->id,
            'items' => ['A', 'B'],
        ]);

        $this->assertSame([
            ['text' => 'A', 'checked' => false],
            ['text' => 'B', 'checked' => false],
        ], $template->toChecklist());
    }
}
