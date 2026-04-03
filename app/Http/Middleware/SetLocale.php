<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->locale, self::SUPPORTED)) {
            app()->setLocale($user->locale);
        } else {
            $locale = $this->detectBrowserLocale($request);
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function detectBrowserLocale(Request $request): string
    {
        $preferred = $request->getPreferredLanguage(self::SUPPORTED);

        return $preferred ?? config('app.locale', 'de');
    }
}
