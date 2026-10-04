<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API responses are Arabic by default; `Accept-Language: en` switches to English.
 */
class SetApiLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED) ?? 'ar';

        // Only honour an explicit header; browsers' defaults shouldn't flip the app.
        app()->setLocale($request->hasHeader('Accept-Language') ? $locale : 'ar');

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
