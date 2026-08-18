<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    /** Formats the branding upload zone advertises and the server accepts. */
    public const LOGO_FORMATS = ['png', 'jpg', 'jpeg', 'webp'];

    public const LOGO_MAX_KB = 2048;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('settings.view'), 403);

        $company = Company::findOrFail($user->company_id);
        $statuses = Status::orderBy('sort_order')->get();

        return Inertia::render('Settings/Index', [
            'company' => $company,
            'calendarSettings' => [
                'show_weekends' => Setting::get('show_weekends', 'false') === 'true',
                'start_hour' => (int) Setting::get('start_hour', '8'),
                'end_hour' => (int) Setting::get('end_hour', '18'),
            ],
            'statuses' => $statuses,
            'checklistTemplates' => ChecklistTemplate::ordered()->get(['id', 'name', 'kind', 'items']),
        ]);
    }

    public function updateCompany(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('settings.edit'), 403);

        // `sometimes` so the branding card can submit a logo on its own without
        // having to echo back every profile field.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:'.implode(',', self::LOGO_FORMATS), 'max:'.self::LOGO_MAX_KB],
        ]);

        $company = Company::findOrFail($user->company_id);

        $data = collect($validated)->except('logo')->toArray();

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $company->update($data);

        return back();
    }

    public function updateCalendar(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('settings.edit'), 403);

        $validated = $request->validate([
            'show_weekends' => ['required', 'boolean'],
            'start_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'end_hour' => ['required', 'integer', 'min:1', 'max:24', 'gt:start_hour'],
        ]);

        Setting::set('show_weekends', $validated['show_weekends'] ? 'true' : 'false');
        Setting::set('start_hour', (string) $validated['start_hour']);
        Setting::set('end_hour', (string) $validated['end_hour']);

        return back();
    }
}
