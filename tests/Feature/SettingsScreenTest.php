<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingController;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The settings screen: what it hands the page, and what the two save endpoints
 * accept — including the branding upload rules the drop zone advertises.
 */
class SettingsScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['name' => 'Wilhelmsen BioTec']);
        $this->owner = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => User::ROLE_OWNER,
        ]);
    }

    public function test_the_screen_ships_every_section_it_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(route('settings.index'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertArrayHasKey('company', $props);
        $this->assertArrayHasKey('calendarSettings', $props);
        $this->assertArrayHasKey('statuses', $props);
        $this->assertArrayHasKey('checklistTemplates', $props);
    }

    public function test_the_screen_is_closed_to_employees_without_settings_view(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($employee)->get(route('settings.index'))->assertForbidden();
    }

    public function test_the_profile_card_saves_without_touching_the_logo(): void
    {
        $this->actingAs($this->owner)->put(route('settings.company.update'), [
            'name' => 'Wilhelmsen BioTec GmbH',
            'street' => 'Hauptstraße 1',
            'zip' => '80331',
            'city' => 'München',
            'phone' => '089 12345',
            'email' => 'info@wilhelmsen-biotec.de',
        ])->assertRedirect();

        $company = $this->company->fresh();
        $this->assertSame('Wilhelmsen BioTec GmbH', $company->name);
        $this->assertSame('80331', $company->zip);
        $this->assertNull($company->logo_path);
    }

    public function test_the_name_may_not_be_blanked(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.company.update'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('Wilhelmsen BioTec', $this->company->fresh()->name);
    }

    public function test_the_branding_card_can_upload_a_logo_on_its_own(): void
    {
        Storage::fake('public');

        // The branding card submits only the file — no echoed profile fields.
        $this->actingAs($this->owner)->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertRedirect();

        $company = $this->company->fresh();
        $this->assertNotNull($company->logo_path);
        $this->assertSame('Wilhelmsen BioTec', $company->name);
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_uploading_a_new_logo_removes_the_old_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);
        $first = $this->company->fresh()->logo_path;

        $this->actingAs($this->owner)->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($this->company->fresh()->logo_path);
    }

    public function test_an_unsupported_logo_format_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->create('brand.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($this->company->fresh()->logo_path);
    }

    public function test_a_logo_over_the_advertised_size_limit_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->put(route('settings.company.update'), [
            'logo' => UploadedFile::fake()->create('huge.png', SettingController::LOGO_MAX_KB + 1, 'image/png'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($this->company->fresh()->logo_path);
    }

    public function test_the_calendar_card_stores_the_display_window(): void
    {
        $this->actingAs($this->owner)->put(route('settings.calendar.update'), [
            'show_weekends' => true,
            'start_hour' => 7,
            'end_hour' => 19,
        ])->assertRedirect();

        $this->actingAs($this->owner);
        $this->assertSame('true', Setting::get('show_weekends'));
        $this->assertSame('7', Setting::get('start_hour'));
        $this->assertSame('19', Setting::get('end_hour'));
    }

    public function test_the_calendar_window_must_end_after_it_starts(): void
    {
        $this->actingAs($this->owner)->put(route('settings.calendar.update'), [
            'show_weekends' => false,
            'start_hour' => 18,
            'end_hour' => 8,
        ])->assertSessionHasErrors('end_hour');
    }

    public function test_saving_settings_needs_the_edit_permission(): void
    {
        $employee = User::factory()->create(['company_id' => $this->company->id]);
        Permission::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'permission' => 'settings.view',
        ]);

        $this->actingAs($employee)->get(route('settings.index'))->assertOk();
        $this->actingAs($employee)
            ->put(route('settings.company.update'), ['name' => 'Fremd'])
            ->assertForbidden();
        $this->actingAs($employee)
            ->put(route('settings.calendar.update'), [
                'show_weekends' => true, 'start_hour' => 8, 'end_hour' => 18,
            ])->assertForbidden();

        $this->assertSame('Wilhelmsen BioTec', $this->company->fresh()->name);
    }
}
