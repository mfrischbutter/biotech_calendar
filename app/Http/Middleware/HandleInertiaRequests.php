<?php

namespace App\Http\Middleware;

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
        $translations = cache()->rememberForever("translations.{$locale}", function () use ($locale) {
            $langFile = lang_path("{$locale}.json");

            return File::exists($langFile)
                ? json_decode(File::get($langFile), true)
                : [];
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->toArray(),
                    'role' => $request->user()->role,
                ] : null,
            ],
            'locale' => $locale,
            'translations' => $translations,
        ];
    }
}
