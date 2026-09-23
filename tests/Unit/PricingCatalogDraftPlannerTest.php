<?php

use App\Assessment\PricingCatalogDraftPlanner;
use App\References\MunicipalFeeCatalog;
use App\References\MunicipalTaxDefinitionEvidence;
use Symfony\Component\Yaml\Yaml;

function pricingDraftSource(): string
{
    return dirname(__DIR__, 2).'/database/seeders/data/ipil_municipal_fee_catalog.v1.yaml';
}

function pricingDraftPlanner(): PricingCatalogDraftPlanner
{
    return new PricingCatalogDraftPlanner(new MunicipalFeeCatalog(new MunicipalTaxDefinitionEvidence));
}

test('preserves every fee row and tax branch without making prices executable', function () {
    $path = pricingDraftSource();
    $source = Yaml::parseFile($path);
    $drafts = pricingDraftPlanner()->plan($path, hash_file('sha256', $path));
    expect($drafts)->toHaveCount(171);
    $branches = 0;
    $taxes = 0;
    foreach ($drafts as $index => $draft) {
        expect($draft->sourceEvidence['fee'])->toBe($source['fees'][$index])
            ->and($draft->method)->toBe('source_observed')
            ->and($draft->amountMinor)->toBeNull()
            ->and($draft->unitCode)->toBeNull();
        expect(fn () => $draft->assertExecutable())->toThrow(LogicException::class);
        $tax = $draft->sourceEvidence['tax_definition'];
        if ($tax !== null) {
            $original = array_values(array_filter($source['tax_definition_evidence']['definitions'],
                fn (array $row): bool => $row['fee_code'] === $draft->code))[0];
            expect($tax['branches'])->toBe($original['branches']);
            $branches += count($tax['branches']);
            $taxes++;
        }
    }
    expect($taxes)->toBe(17)->and($branches)->toBe(245);
    expect(serialize(pricingDraftPlanner()->plan($path, hash_file('sha256', $path))))
        ->toBe(serialize($drafts));
});

test('rejects missing or mismatched source fingerprints', function (string $hash) {
    expect(fn () => pricingDraftPlanner()->plan(pricingDraftSource(), $hash))
        ->toThrow(LogicException::class);
})->with(['', str_repeat('0', 64), 'invalid']);

test('rejects duplicate fee identities even with a matching file fingerprint', function () {
    $source = Yaml::parseFile(pricingDraftSource());
    $taxCodes = array_column($source['tax_definition_evidence']['definitions'], 'fee_code');
    $fee = array_values(array_filter($source['fees'], fn (array $row): bool => ! in_array($row['code'], $taxCodes, true)))[0];
    $source['fees'][] = $fee;
    $path = tempnam(sys_get_temp_dir(), 'pricing-draft-');
    try {
        file_put_contents($path, Yaml::dump($source, 20));
        expect(fn () => pricingDraftPlanner()->plan($path, hash_file('sha256', $path)))
            ->toThrow(LogicException::class, 'Duplicate source fee identity');
    } finally {
        unlink($path);
    }
});
