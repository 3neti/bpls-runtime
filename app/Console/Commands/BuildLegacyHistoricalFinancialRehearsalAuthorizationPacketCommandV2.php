<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('legacy:build-historical-preservation-authorization-packet
    {mapping-set : Exact frozen accepted mapping-set ID}
    {--run-id= : Stable preservation planning reference}
    {--authorized : Record that the Board authorized this exact bounded rehearsal}
    {--authorization-reference= : Durable Board authorization reference}
    {--json : Write only structured output}')]
#[Description('Regenerate and freeze a payload-safe bounded historical preservation rehearsal authorization packet without executing it.')]
class BuildLegacyHistoricalFinancialRehearsalAuthorizationPacketCommandV2 extends BuildLegacyHistoricalFinancialRehearsalAuthorizationPacketCommand {}
