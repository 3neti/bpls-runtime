<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RecordAssessmentDecision;
use App\Enums\AssessmentDecisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ApproveAssessmentRequest;
use App\Http\Requests\Staff\ReturnAssessmentForCorrectionRequest;
use App\Models\Assessment;
use DomainException;
use Illuminate\Http\RedirectResponse;

class AssessmentDecisionController extends Controller
{
    public function approve(
        ApproveAssessmentRequest $request,
        Assessment $assessment,
        RecordAssessmentDecision $recordDecision,
    ): RedirectResponse {
        try {
            $recordDecision->handle(
                $assessment,
                $request->user(),
                AssessmentDecisionAction::Approved,
                $request->validated('assessment_snapshot_hash'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['assessment_decision' => $exception->getMessage()]);
        }

        return to_route('staff.permit-applications.assessments.show', $assessment)
            ->with('status', 'Assessment amount approved for payment by the Municipal Treasurer.');
    }

    public function returnForCorrection(
        ReturnAssessmentForCorrectionRequest $request,
        Assessment $assessment,
        RecordAssessmentDecision $recordDecision,
    ): RedirectResponse {
        try {
            $recordDecision->handle(
                $assessment,
                $request->user(),
                AssessmentDecisionAction::ReturnedForCorrection,
                $request->validated('assessment_snapshot_hash'),
                $request->validated('reason'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['assessment_decision' => $exception->getMessage()]);
        }

        return to_route('staff.permit-applications.assessments.show', $assessment)
            ->with('status', 'Assessment returned for correction. Payment remains unavailable.');
    }
}
