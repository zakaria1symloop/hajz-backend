<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', 'en');

        // Extract primary language code (e.g., 'en-US' -> 'en')
        $locale = substr($locale, 0, 2);

        // Only allow supported locales
        if (!in_array($locale, ['en', 'fr', 'ar'])) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
