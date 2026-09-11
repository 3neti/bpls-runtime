<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestrictIpilHistoricalUat
{
    public const EnvironmentId = 'env-a2b8d5b4-7ab8-4c99-a711-a88053b7fde5';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ipil_historical_uat.enabled') && ! app()->environment('historical-uat')) {
            return $next($request);
        }

        $reviewer = config('ipil_historical_uat.reviewer_email');
        abort_unless(
            config('ipil_historical_uat.enabled') === true
            && app()->environment('historical-uat', 'testing')
            && config('ipil_historical_uat.environment_id') === self::EnvironmentId
            && config('stakeholder_preview.mode') === false
            && is_string($reviewer) && filter_var($reviewer, FILTER_VALIDATE_EMAIL),
            503,
        );

        $user = $request->user();
        if ($user !== null) {
            abort_unless(hash_equals(strtolower($reviewer), strtolower($user->email)), 403);
        }

        if ($request->isMethod('GET') && ($request->is('/') || $request->routeIs('dashboard'))) {
            $response = redirect()->route($request->user() ? 'staff.ipil-history.index' : 'login');
        } else {
            // Existing Laravel sign-in remains the authentication boundary. No preview,
            // registration, account claiming, operational route, or export is admitted.
            $authentication = $request->routeIs('login', 'login.store', 'logout', 'two-factor.login', 'two-factor.login.store');
            $history = $request->routeIs('staff.ipil-history.*') && in_array($request->method(), ['GET', 'HEAD'], true);
            abort_unless($authentication || $history, 404);
            $response = $next($request);
        }

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
