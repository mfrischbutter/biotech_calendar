<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $langFile = lang_path("{$locale}.json");
        $translations = File::exists($langFile)
            ? json_decode(File::get($langFile), true)
            : [];

        $user = $request->user();
        $company = $user ? Company::find($user->company_id) : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'role' => $user->role,
                    'company_id' => $user->company_id,
                    'permissions' => $user->isOwner()
                        ? array_keys(Permission::ALL)
                        : $user->permissions->pluck('permission')->toArray(),
                ] : null,
            ],
            'companyBranding' => $company ? [
                'name' => $company->name,
                'logo_url' => $company->logo_path ? '/storage/'.$company->logo_path : null,
            ] : null,
            'googlePlacesApiKey' => config('services.google.places_api_key'),
            'locale' => $locale,
            'translations' => $translations,
        ];
    }
}
