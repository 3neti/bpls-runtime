<?php

namespace App\Console\Commands;

use App\Actions\ActivateLegacyUatFeeCatalog;
use App\Enums\UserPermission;
use App\Models\LegacyImportBatch;
use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'legacy:activate-uat-fee-catalog', description: 'Use a characterized legacy Ipil catalogue as the Laboratory/UAT Price List.')]
class ActivateLegacyUatFeeCatalogCommand extends Command
{
    protected $signature = 'legacy:activate-uat-fee-catalog
        {batch : Staged legacy import batch ID}
        {--effective-from=2025-01-01 : First application date covered by the UAT catalogue}
        {--actor-email= : Price List manager recorded as the decision actor}
        {--json : Emit machine-readable output}';

    public function handle(ActivateLegacyUatFeeCatalog $activate): int
    {
        $actorEmail = $this->option('actor-email');
        $actor = is_string($actorEmail) && $actorEmail !== ''
            ? User::query()->where('email', $actorEmail)->first()
            : User::query()->get()->first(fn (User $user): bool => $user->can(UserPermission::ManageFeeRules->value));
        if (! $actor instanceof User) {
            $this->error('No authorized Price List manager is available.');

            return self::FAILURE;
        }

        $result = $activate->handle(
            LegacyImportBatch::query()->findOrFail((int) $this->argument('batch')),
            (string) $this->option('effective-from'),
            $actor,
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Migrated Ipil Price List activated for Laboratory/UAT.');
        foreach ($result as $label => $value) {
            $this->line(str($label)->headline()->toString().': '.$value);
        }

        return self::SUCCESS;
    }
}
