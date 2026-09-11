<?php

namespace App\Console\Commands;

use App\Actions\AuditIpilHistoricalMaterialization;
use App\Actions\VerifyIpilRescueCorpus;
use App\Support\IpilRescue\Gate6ExecutionAuthorization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ipil:audit {corpus : Local rescue corpus path} {--manifest= : Exact accepted private Execution Manifest path} {--json : Emit JSON}')]
#[Description('Audit the local PostgreSQL Ipil historical materialization against the accepted corpus and manifest anchors.')]
final class IpilAuditCommand extends Command
{
    public function handle(VerifyIpilRescueCorpus $verify, AuditIpilHistoricalMaterialization $audit): int
    {
        $verified = false;

        try {
            $result = $verify->handle((string) $this->argument('corpus'));
            $verified = true;
            $manifestPath = (string) $this->option('manifest');
            $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR) : null;
            if (! is_array($manifest) || ! Gate6ExecutionAuthorization::hasAcceptedManifestFingerprint($manifest)) {
                throw new \RuntimeException('The exact accepted Gate 5 Execution Manifest is required for audit.');
            }
            $auditResult = $audit->handle();
        } catch (Throwable $exception) {
            return $this->failure($exception, $verified);
        }

        if ($this->option('json')) {
            $this->line($this->json($auditResult + [
                'corpus_integrity_passed' => true,
                'verified' => true,
                'audited' => true,
                'offline' => ! app()->environment('historical-uat'),
                'domain_writes' => false,
                'corpus_id' => $result->corpusId,
            ]));
        } else {
            $this->line('IPIL HISTORICAL MATERIALIZATION AUDIT');
            $this->line($auditResult['passed'] ? 'PASS' : 'FAIL');
            $this->line('Completed payment anchor: PHP '.$auditResult['anchors']['completed_payments_php']);
        }

        return $auditResult['passed'] ? self::SUCCESS : self::FAILURE;
    }

    private function failure(Throwable $exception, bool $verified = false): int
    {
        if ($this->option('json')) {
            $this->line($this->json([
                'passed' => false,
                'corpus_integrity_passed' => $verified,
                'verified' => $verified,
                'audited' => false,
                'offline' => ! app()->environment('historical-uat'),
                'domain_writes' => false,
                'error' => $exception->getMessage(),
            ]));
        } else {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }

    /** @param  array<string, mixed>  $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
