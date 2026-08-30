<?php

namespace App\Console\Commands;

use App\LifecycleScenarios\RenewalHappyPathDefinition;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bpls:lifecycle:list {--json : Emit JSON}')]
#[Description('List BPLS lifecycle certification scenarios without executing them.')]
class ListBplsLifecycleScenariosCommand extends Command
{
    public function handle(RenewalHappyPathDefinition $definition): int
    {
        $scenario = $definition->describe();
        $result = [[
            'id' => $scenario['id'],
            'label' => $scenario['label'],
            'business_question' => $scenario['business_question'],
            'status' => 'available',
        ]];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->table(['Scenario', 'Status', 'Business question'], [[
            $scenario['id'],
            'AVAILABLE',
            $scenario['business_question'],
        ]]);

        return self::SUCCESS;
    }
}
