<?php

namespace App\Enums;

enum BusinessPermitEvaluationRevisionAction: string
{
    case Declaration = 'declaration';
    case Proposal = 'proposal';
    case Confirmation = 'confirmation';
    case Correction = 'correction';
    case AuthorizedDetermination = 'authorized_determination';
    case Supersession = 'supersession';
}
