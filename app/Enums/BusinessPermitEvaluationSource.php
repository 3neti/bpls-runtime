<?php

namespace App\Enums;

enum BusinessPermitEvaluationSource: string
{
    case ApplicantDeclaration = 'applicant_declaration';
    case GovernedRule = 'governed_rule';
    case ConfiguredMunicipalDefault = 'configured_municipal_default';
    case GovernedOfficeProcedure = 'governed_office_procedure';
    case BoardOperationalRecollection = 'board_operational_recollection';
    case ProvisionalUat = 'provisional_uat';

    public function isCommissionedChargeSource(): bool
    {
        return in_array($this, [
            self::GovernedRule,
            self::ConfiguredMunicipalDefault,
            self::GovernedOfficeProcedure,
        ], true);
    }
}
