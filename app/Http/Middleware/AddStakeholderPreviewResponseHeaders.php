<?php

namespace App\Http\Middleware;

use App\StakeholderPreview\StakeholderPreviewSafety;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddStakeholderPreviewResponseHeaders
{
    public function __construct(private StakeholderPreviewSafety $safety) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->safety->isEnabled()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
