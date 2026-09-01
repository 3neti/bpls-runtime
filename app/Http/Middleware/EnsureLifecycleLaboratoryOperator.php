<?php

namespace App\Http\Middleware;

use App\Enums\StakeholderPreviewPersona;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureLifecycleLaboratoryOperator
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $this->safety->ensureReady();
        abort_unless($this->safety->personaFor($request->user()) === StakeholderPreviewPersona::Management, 404);

        return $next($request);
    }
}
