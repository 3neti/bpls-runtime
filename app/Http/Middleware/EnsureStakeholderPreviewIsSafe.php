<?php

namespace App\Http\Middleware;

use App\StakeholderPreview\StakeholderPreviewSafety;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStakeholderPreviewIsSafe
{
    public function __construct(private StakeholderPreviewSafety $safety) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->safety->ensureReady();

        return $next($request);
    }
}
