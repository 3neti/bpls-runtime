<?php

namespace App\Console\Commands;

use App\Actions\ApplyDueBploRoutingSuggestions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bpls:routing-sentinel')]
#[Description('Apply due laboratory BPLO routing suggestions through the canonical routing action')]
class ApplyDueBploRoutingSuggestionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ApplyDueBploRoutingSuggestions $applyDueSuggestions): int
    {
        $result = $applyDueSuggestions->handle();
        $this->components->info("Routing sentinel: {$result['armed']} armed, {$result['defaulted']} defaulted, {$result['invalidated']} invalidated.");

        return self::SUCCESS;
    }
}
