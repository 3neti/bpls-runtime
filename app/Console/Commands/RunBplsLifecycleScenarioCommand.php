<?php

namespace App\Console\Commands;

use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathFailure;
use App\LifecycleScenarios\RenewalHappyPathScenario;
use App\LifecycleScenarios\ScenarioArtifactStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

#[Signature('bpls:lifecycle:run {scenario : Stable BPLS lifecycle scenario id} {--json : Emit only the canonical JSON result}')]
#[Description('Run one deterministic BPLS lifecycle certification scenario.')]
class RunBplsLifecycleScenarioCommand extends Command
{
    public function handle(RenewalHappyPathScenario $renewalHappyPath): int
    {
        $scenarioId = (string) $this->argument('scenario');
        $artifactStore = new ScenarioArtifactStore($scenarioId, RenewalHappyPathDefinition::RunId);

        if ($scenarioId !== RenewalHappyPathDefinition::Id) {
            return $this->failure($artifactStore, new RenewalHappyPathFailure(
                'Requested lifecycle scenario is implemented',
                "Scenario [{$scenarioId}] is NOT RUN. Use bpls:lifecycle:list.",
            ));
        }

        try {
            $result = $renewalHappyPath->run();
            $artifactStore->putJson('result.json', $result);
            $artifactStore->putJson('action-trace.json', ['actions' => $result['action_trace']]);

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $this->renderHumanResult($result, $artifactStore);

            return self::SUCCESS;
        } catch (RenewalHappyPathFailure $failure) {
            return $this->failure($artifactStore, $failure);
        } catch (Throwable $exception) {
            return $this->failure($artifactStore, new RenewalHappyPathFailure(
                'Scenario 01 completed without an unclassified engine exception',
                $exception->getMessage(),
                $exception,
            ));
        }
    }

    /** @param array<string, mixed> $result */
    private function renderHumanResult(array $result, ScenarioArtifactStore $artifactStore): void
    {
        $this->info('BPLS LIFECYCLE SCENARIO 01 — RENEWAL HAPPY PATH: PASS');
        $this->line('Question: '.$result['business_question']);
        $this->line('Application: Renewal #'.$result['application']['id'].' · '.$result['application']['business']['name']);
        $this->newLine();
        $this->line('FINANCIAL WORKING PAPER');

        foreach ($result['lines_of_business'] as $lineOfBusiness) {
            $this->line($lineOfBusiness['name']);
            foreach ($lineOfBusiness['charges'] as $charge) {
                $this->line('  '.$charge['label'].' · '.$charge['responsible_party'].' · '.$this->pesos($charge['amount_cents']));
            }
            $this->line('  Subtotal: '.$this->pesos($lineOfBusiness['subtotal_amount_cents']));
        }

        $this->line('Application-wide');
        $this->line('  Business Inspection Fee · accepted governed rule · '.$this->pesos($result['evaluation']['subtotals']['application_wide_amount_cents']));
        $this->line('Grand Total: '.$this->pesos($result['evaluation']['grand_total_amount_cents']));
        $this->newLine();
        $this->line('Responsibilities: '.$result['responsibilities']['resolved_count'].'/'.$result['responsibilities']['created_count'].' resolved');
        $this->line('Evaluation: Ready · version '.$result['evaluation']['version_sequence']);
        $this->line('Assessment: #'.$result['assessment']['id'].' · '.$this->pesos($result['assessment']['total_amount_cents']).' · Prepared by '.$result['assessment']['prepared_by']['name']);
        $this->line('Treasury: completed, no correction');
        $this->line('Municipal Treasurer: approved exact Assessment');
        $this->line('Payable: Payment Schedule #'.$result['payment_schedule']['id'].' · '.$this->pesos($result['payable']['amount_cents']));
        $this->line('Database: '.$result['database_driver'].' · Semantic result '.$result['semantic_result_hash']);
        $this->line('JSON: '.$artifactStore->absolutePath().'/result.json');
    }

    private function failure(ScenarioArtifactStore $artifactStore, RenewalHappyPathFailure $failure): int
    {
        $result = [
            'schema_version' => 'bpls.lifecycle-certification.v1',
            'scenario_id' => $artifactStore->scenarioKey,
            'status' => 'failed',
            'business_question' => RenewalHappyPathDefinition::EvidenceQuestion,
            'first_failure' => [
                'invariant' => $failure->invariant,
                'message' => $failure->getMessage(),
            ],
            'artifacts' => [
                'root' => $artifactStore->rootRelativePath(),
                'json' => 'result.json',
            ],
        ];
        $artifactStore->putJson('result.json', $result);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->error($failure->getMessage());
            $this->line('JSON: '.$artifactStore->absolutePath().'/result.json');
        }

        return self::FAILURE;
    }

    private function pesos(int $amountCents): string
    {
        return 'PHP '.Number::format($amountCents / 100, precision: 2);
    }
}
