<?php

declare(strict_types=1);

use App\Assessment\PricingDefinitionDraft;
use App\Assessment\PricingDraftResolution;
use App\Assessment\PricingDraftResolver;
use App\Exceptions\UnsupportedAssessmentPolicy;

function resolutionDraft(array $changes = []): PricingDefinitionDraft
{
    return new PricingDefinitionDraft(...array_replace([
        'code' => 'FEE', 'revision' => 1, 'method' => 'fixed', 'basis' => 'none',
        'unitCode' => null, 'amountMinor' => 10000, 'currency' => 'PHP',
        'revenueAccountCode' => null, 'sourceSha256' => hash('sha256', 'source'),
        'sourceLocator' => 'test', 'sourceEvidence' => [],
    ], $changes));
}

test('draft fixed values including zero never become resolved prices', function (int $amount) {
    $draft = resolutionDraft(['amountMinor' => $amount, 'sourceEvidence' => ['accepted' => true, 'executable' => true]]);
    $result = (new PricingDraftResolver)->resolve([$draft], 'FEE', 1, $draft->sourceSha256);
    expect($result->status)->toBe('policy_disabled')
        ->and($result->snapshot()['amount_minor'])->toBeNull()
        ->and($result->snapshot()['executable'])->toBeFalse();
    expect(fn () => $result->requirePriceComponent())->toThrow(UnsupportedAssessmentPolicy::class);
})->with([0, 10000]);

test('requires exact code and revision without latest or name fallback', function () {
    $draft = resolutionDraft();
    foreach ([['OTHER', 1], ['FEE', 2], ['fee', 1]] as [$code, $revision]) {
        $result = (new PricingDraftResolver)->resolve([$draft], $code, $revision, $draft->sourceSha256);
        expect($result->status)->toBe('no_match');
        expect(fn () => $result->requirePriceComponent())->toThrow(UnsupportedAssessmentPolicy::class);
    }
});

test('rejects ambiguous matches instead of using the first amount', function () {
    $first = resolutionDraft();
    $second = resolutionDraft(['amountMinor' => 20000]);
    $resolver = new PricingDraftResolver;
    $result = $resolver->resolve([$first, $second], 'FEE', 1, $first->sourceSha256);
    expect($result->status)->toBe('ambiguous_match')
        ->and($resolver->resolve([$second, $first], 'FEE', 1, $first->sourceSha256)->snapshot())->toBe($result->snapshot());
    expect(fn () => $result->requirePriceComponent())->toThrow(UnsupportedAssessmentPolicy::class);
});

test('preserves source conflict rather than filtering inconvenient candidates', function () {
    $first = resolutionDraft();
    $second = resolutionDraft(['sourceSha256' => hash('sha256', 'different')]);
    $result = (new PricingDraftResolver)->resolve([$first, $second], 'FEE', 1, $first->sourceSha256);
    expect($result->status)->toBe('conflicting_source');
    expect(fn () => $result->requirePriceComponent())->toThrow(UnsupportedAssessmentPolicy::class);
});

test('unresolved and quantity drafts remain disabled', function (array $changes) {
    $draft = resolutionDraft($changes);
    expect((new PricingDraftResolver)->resolve([$draft], 'FEE', 1, $draft->sourceSha256)->status)->toBe('policy_disabled');
})->with([
    [['method' => 'manual_determination', 'amountMinor' => null]],
    [['method' => 'source_observed', 'amountMinor' => null]],
    [['method' => 'quantity_rate', 'basis' => 'employee_count', 'unitCode' => 'person']],
]);

test('rejects invalid request and invented successful result', function () {
    expect(fn () => (new PricingDraftResolver)->resolve([], 'FEE', 1, 'bad'))->toThrow(InvalidArgumentException::class);
    expect(fn () => (new PricingDraftResolver)->resolve([new stdClass], 'FEE', 1, hash('sha256', 'source')))->toThrow(InvalidArgumentException::class);
    expect(fn () => new PricingDraftResolution('resolved', 1))->toThrow(InvalidArgumentException::class);
    expect(fn () => new PricingDraftResolution('policy_disabled', 0))->toThrow(InvalidArgumentException::class);
});
