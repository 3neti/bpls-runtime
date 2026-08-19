<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RecordAssessmentDecision;
use App\Enums\AssessmentDecisionAction;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ReturnAssessmentForCorrectionRequest;
use App\Models\Assessment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AssessmentDecisionController extends Controller
{
    public function approve(Assessment $assessment, RecordAssessmentDecision $recordDecision): RedirectResponse
    {
        Gate::authorize(UserPermission::ApproveAssessments->value);

        $recordDecision->handle(
            $assessment,
            auth()->user(),
            AssessmentDecisionAction::Approved,
        );

        return to_route('staff.permit-applications.assessments.show', $assessment)
            ->with('status', 'Assessment amount approved for payment by the Municipal Treasurer.');
    }

    public function returnForCorrection(
        ReturnAssessmentForCorrectionRequest $request,
        Assessment $assessment,
        RecordAssessmentDecision $recordDecision,
    ): RedirectResponse {
        $recordDecision->handle(
            $assessment,
            $request->user(),
            AssessmentDecisionAction::ReturnedForCorrection,
            $request->validated('reason'),
        );

        return to_route('staff.permit-applications.assessments.show', $assessment)
            ->with('status', 'Assessment returned for correction. Payment remains unavailable.');
    }
}
