<?php

namespace App\Console\Commands;

use App\Actions\ResolveAuthorizedLegacyCitizenPermitApplicationLabBundle;
use App\Actions\ResolveLegacyCitizenPermitApplicationLabPool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Signature('lifecycle:export-stakeholder-preview-legacy-specimens
    {--output= : Required absolute path under the operating-system temporary directory}')]
#[Description('Export the six authorized local legacy specimens for secure Laravel Cloud secret transport')]
class ExportStakeholderPreviewLegacySpecimenBundle extends Command
{
    public function handle(
        ResolveLegacyCitizenPermitApplicationLabPool $resolveLocalPool,
        ResolveAuthorizedLegacyCitizenPermitApplicationLabBundle $bundle,
        Filesystem $files,
    ): int {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Authorized legacy specimen export is local/testing-only.');

            return self::FAILURE;
        }

        $output = $this->option('output');
        $temporaryRoot = realpath(sys_get_temp_dir());
        $outputDirectory = is_string($output) ? realpath(dirname($output)) : false;

        if (! is_string($output)
            || ! is_string($temporaryRoot)
            || ! is_string($outputDirectory)
            || ! str_starts_with($outputDirectory, $temporaryRoot.DIRECTORY_SEPARATOR.'bpls-authorized-legacy-')
            || is_link(dirname($output))
            || file_exists($output)
            || is_link($output)) {
            $this->error('The output must be a new file inside a real bpls-authorized-legacy-* temporary directory.');

            return self::FAILURE;
        }

        config()->set([
            'stakeholder_preview.legacy_lab_specimen_bundle' => null,
            'stakeholder_preview.legacy_lab_specimen_pool_sha256' => null,
        ]);

        $specimens = $resolveLocalPool->handle();

        if (count($specimens) !== count(ResolveLegacyCitizenPermitApplicationLabPool::SourceBusinessCategories)) {
            $this->error('The local source did not resolve the exact six-specimen review pool.');

            return self::FAILURE;
        }

        $files->put($output, $bundle->encode($specimens), true);
        chmod($output, 0600);

        $this->components->info('Authorized legacy specimen bundle exported for secret transport.');
        $this->line('Specimens: '.count($specimens));
        $this->line('Source-pool SHA-256: '.$bundle->fingerprint($specimens));
        $this->line('Output: '.$output);

        return self::SUCCESS;
    }
}
