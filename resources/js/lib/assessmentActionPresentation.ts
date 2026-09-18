import type { EvaluationLatestAssessment } from '@/types';

export type AssessmentActionPresentation = {
    currentAssessmentId: number | null;
    heading: string;
    ownerLabel: string;
    statusLabel: string;
    totalLabel: string;
    totalAmountCents: number;
    description: string;
    canPrepare: boolean;
};

export function assessmentActionPresentation(
    latestAssessment: EvaluationLatestAssessment | null | undefined,
    assessmentReady: boolean,
    emergingTotalAmountCents: number,
    treasuryCounterCheckComplete = false,
): AssessmentActionPresentation {
    const currentAssessment =
        latestAssessment != null &&
        latestAssessment.superseded === false &&
        latestAssessment.consumes_current_evaluation === true
            ? latestAssessment
            : null;

    if (currentAssessment !== null) {
        const decisionRecorded = currentAssessment.decision !== null;

        return {
            currentAssessmentId: currentAssessment.id,
            heading: `Assessment #${currentAssessment.sequence}`,
            ownerLabel: decisionRecorded
                ? 'Assessment status'
                : treasuryCounterCheckComplete
                  ? 'Next actor — Municipal Treasurer'
                  : 'Next actor — Treasury',
            statusLabel: 'Prepared',
            totalLabel: 'Assessment total',
            totalAmountCents: currentAssessment.total_amount_cents,
            description: decisionRecorded
                ? 'The Assessment decision is recorded. View the Assessment for its current review and payment status.'
                : treasuryCounterCheckComplete
                  ? 'Treasury counter-check is complete. Municipal Treasurer approval or return is next.'
                  : 'The immutable Assessment is prepared from this exact Evaluation. Treasury counter-check is next.',
            canPrepare: false,
        };
    }

    return {
        currentAssessmentId: null,
        heading: 'Prepare Assessment',
        ownerLabel: 'Assessment Officer',
        statusLabel: 'Pending',
        totalLabel: 'Current total',
        totalAmountCents: emergingTotalAmountCents,
        description:
            'Freeze the current total as the Assessment. Treasurer approval follows.',
        canPrepare: assessmentReady,
    };
}
