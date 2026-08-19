<?php

namespace App\Actions;

use App\Enums\StakeholderPreviewPersona;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateStakeholderPreviewPersona
{
    public function __construct(private StakeholderPreviewSafety $safety) {}

    public function handle(Request $request, StakeholderPreviewPersona $persona, bool $requireCurrentPreviewAccount = false): User
    {
        $this->safety->ensureEnabled();

        if ($requireCurrentPreviewAccount && ! $this->safety->personaFor($request->user()) instanceof StakeholderPreviewPersona) {
            abort(403);
        }

        $user = $this->safety->account($persona);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $authenticatedUser = Auth::guard('web')->loginUsingId($user->getAuthIdentifier());
        $request->session()->regenerate();

        if (! $authenticatedUser instanceof User) {
            abort(404);
        }

        return $authenticatedUser;
    }
}
