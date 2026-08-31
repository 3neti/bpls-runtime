<?php

namespace App\Console\Commands;

use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bpls:lifecycle:list {--json : Emit JSON}')]
#[Description('List BPLS lifecycle certification scenarios without executing them.')]
class ListBplsLifecycleScenariosCommand extends Command
{
    public function handle(RenewalHappyPathDefinition $renewal, NewApplicationHappyPathDefinition $newApplication): int
    {
        $scenarios = [$renewal->describe(), $newApplication->describe()];
        $result = collect($scenarios)->map(fn (array $scenario): array => [
            'id' => $scenario['id'],
            'label' => $scenario['label'],
            'business_question' => $scenario['business_question'],
            'status' => 'available',
        ])->all();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->table(['Scenario', 'Status', 'Business question'], collect($scenarios)->map(fn (array $scenario): array => [
            $scenario['id'],
            'AVAILABLE',
            $scenario['business_question'],
        ])->all());

        return self::SUCCESS;
    }
}
