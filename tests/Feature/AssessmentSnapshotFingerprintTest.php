<?php

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCategory;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\PermitApplication;
use Illuminate\Database\Eloquent\Model;

/**
 * A persisted Assessment with two lines, carrying the kinds of string
 * identifiers that must never be coerced to numbers.
 */
function fingerprintedAssessment(): Assessment
{
    $application = PermitApplication::factory()->create(['application_year' => 2026]);
    $assessment = Assessment::factory()->for($application)->create([
        'sequence' => 1,
        'status' => AssessmentStatus::Computed,
        'assessed_at' => now()->startOfSecond(),
        'superseded_at' => null,
        'total_amount_cents' => 30_000,
        'source_snapshot' => [
            'policy' => 'fingerprint canonicalization fixture',
            'tracking_reference' => 'PVA-0012345',
            'or_number' => '0000123',
        ],
    ]);

    AssessmentLine::factory()->for($assessment)->create([
        'code' => 'FEE-A',
        'name' => 'Fee A',
        'basis' => 'none',
        'basis_amount_cents' => 0,
        'amount_cents' => 10_000,
        'legal_basis' => 'Fixture ordinance A',
        'rule_snapshot' => ['legacy_source_id' => 'SUB-0001'],
    ]);

    AssessmentLine::factory()->for($assessment)->create([
        'code' => 'FEE-B',
        'name' => 'Fee B',
        'basis' => 'declared_gross_sales',
        'basis_amount_cents' => 750_000,
        'amount_cents' => 20_000,
        'legal_basis' => 'Fixture ordinance B',
        'rule_snapshot' => ['range' => ['amount_cents' => 20_000]],
    ]);

    return $assessment->load(['lines' => fn ($query) => $query->orderBy('id')]);
}

function fingerprintOf(Assessment $assessment): string
{
    return app(AssessmentSnapshotFingerprint::class)->hash($assessment);
}

/**
 * Set an attribute exactly the way Eloquent hydration and mass assignment do,
 * so a test can hand a model the representation a database driver returned
 * without fighting the declared property type.
 */
function asDriverReturned(Model $model, string $attribute, mixed $value): void
{
    $model->setAttribute($attribute, $value);
}

it('hashes a string amount and an integer amount identically because the amount is the same', function () {
    $assessment = fingerprintedAssessment();

    // PostgreSQL computes SUM(bigint) as numeric, which pdo_pgsql returns as a
    // string. That representation must not change the immutable fingerprint.
    asDriverReturned($assessment, 'total_amount_cents', '10000');
    $stringHash = fingerprintOf($assessment);

    asDriverReturned($assessment, 'total_amount_cents', 10_000);
    $integerHash = fingerprintOf($assessment);

    expect($stringHash)->toBe($integerHash);
});

it('still hashes a genuinely different amount differently', function () {
    $assessment = fingerprintedAssessment();

    asDriverReturned($assessment, 'total_amount_cents', 10_000);
    $original = fingerprintOf($assessment);

    asDriverReturned($assessment, 'total_amount_cents', 10_001);

    expect(fingerprintOf($assessment))->not->toBe($original);

    // A one-centavo difference expressed as a string is still a real difference.
    asDriverReturned($assessment, 'total_amount_cents', '10001');

    expect(fingerprintOf($assessment))->not->toBe($original);
});

it('canonicalizes string representations of line amounts and identifiers', function () {
    $assessment = fingerprintedAssessment();
    $line = $assessment->lines->first();
    $baseline = fingerprintOf($assessment);

    asDriverReturned($line, 'amount_cents', (string) $line->amount_cents);
    asDriverReturned($line, 'basis_amount_cents', (string) $line->basis_amount_cents);
    asDriverReturned($line, 'fee_rule_id', null);
    asDriverReturned($assessment, 'permit_application_id', (string) $assessment->permit_application_id);
    asDriverReturned($assessment, 'sequence', (string) $assessment->sequence);

    expect(fingerprintOf($assessment))->toBe($baseline);
});

it('keeps an absent identifier distinct from identifier zero', function () {
    $assessment = fingerprintedAssessment();
    $line = $assessment->lines->first();

    $line->fee_rule_id = null;
    $absent = fingerprintOf($assessment);

    $line->fee_rule_id = 0;

    expect(fingerprintOf($assessment))->not->toBe($absent);
});

it('exposes integer-domain fields as integers and leaves identifiers as strings', function () {
    $assessment = fingerprintedAssessment();
    asDriverReturned($assessment, 'total_amount_cents', '30000');
    $snapshot = app(AssessmentSnapshotFingerprint::class)->snapshot($assessment);
    /** @var array<int, array<string, mixed>> $lines */
    $lines = $snapshot['lines'];
    $line = collect($lines)->firstWhere('code', 'FEE-A');

    expect($snapshot['total_amount_cents'])->toBeInt()->toBe(30_000)
        ->and($snapshot['assessment_id'])->toBeInt()
        ->and($snapshot['permit_application_id'])->toBeInt()
        ->and($snapshot['sequence'])->toBeInt()
        ->and($snapshot['assessed_by_id'])->toBeNull()
        ->and($line['amount_cents'])->toBeInt()
        ->and($line['basis_amount_cents'])->toBeInt()
        ->and($line['fee_rule_id'])->toBeNull()
        ->and($line['code'])->toBeString()->toBe('FEE-A')
        ->and($line['basis'])->toBeString()->toBe('none')
        ->and($line['legal_basis'])->toBeString()
        ->and($snapshot['status'])->toBe('computed');

    // Opaque payloads keep their string identifiers, leading zeroes intact.
    expect($snapshot['source_snapshot']['tracking_reference'])->toBeString()->toBe('PVA-0012345')
        ->and($snapshot['source_snapshot']['or_number'])->toBeString()->toBe('0000123')
        ->and($line['rule_snapshot']['legacy_source_id'])->toBeString()->toBe('SUB-0001');
});

it('never coerces string identifiers that differ only by leading zeroes', function () {
    $assessment = fingerprintedAssessment();
    $line = $assessment->lines->first();

    $line->code = '0012345';
    $paddedCode = fingerprintOf($assessment);

    $line->code = '12345';

    expect(fingerprintOf($assessment))->not->toBe($paddedCode);

    $assessment->source_snapshot = ['tracking_reference' => 'PVA-0012345'];
    $paddedReference = fingerprintOf($assessment);

    $assessment->source_snapshot = ['tracking_reference' => 'PVA-12345'];

    expect(fingerprintOf($assessment))->not->toBe($paddedReference);
});

it('changes when a line amount, basis amount, or rule snapshot changes', function () {
    $assessment = fingerprintedAssessment();
    $line = $assessment->lines->first();
    $baseline = fingerprintOf($assessment);

    $line->amount_cents = 10_500;
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $line->amount_cents = 10_000;
    $line->basis_amount_cents = 1;
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $line->basis_amount_cents = 0;
    $line->rule_snapshot = ['legacy_source_id' => 'SUB-0002'];
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $line->rule_snapshot = ['legacy_source_id' => 'SUB-0001'];
    expect(fingerprintOf($assessment))->toBe($baseline);
});

it('changes when a line is added or removed', function () {
    $assessment = fingerprintedAssessment();
    $baseline = fingerprintOf($assessment);

    $added = AssessmentLine::factory()->for($assessment)->create([
        'code' => 'FEE-C',
        'amount_cents' => 5_000,
        'basis_amount_cents' => 0,
        'rule_snapshot' => [],
    ]);
    $assessment->load(['lines' => fn ($query) => $query->orderBy('id')]);
    $withExtraLine = fingerprintOf($assessment);

    expect($withExtraLine)->not->toBe($baseline);

    $added->delete();
    $assessment->load(['lines' => fn ($query) => $query->orderBy('id')]);

    expect(fingerprintOf($assessment))->toBe($baseline);

    $assessment->lines->first()->delete();
    $assessment->load(['lines' => fn ($query) => $query->orderBy('id')]);

    expect(fingerprintOf($assessment))->not->toBe($baseline);
});

it('canonicalizes line order by id while still detecting reassigned amounts', function () {
    $assessment = fingerprintedAssessment();
    $baseline = fingerprintOf($assessment);

    $assessment->setRelation('lines', $assessment->lines->reverse()->values());

    expect(fingerprintOf($assessment))->toBe($baseline);

    $assessment->load(['lines' => fn ($query) => $query->orderBy('id')]);
    [$first, $second] = [$assessment->lines[0], $assessment->lines[1]];
    [$first->amount_cents, $second->amount_cents] = [$second->amount_cents, $first->amount_cents];

    expect(fingerprintOf($assessment))->not->toBe($baseline);
});

it('changes when other financially relevant assessment state changes', function () {
    $assessment = fingerprintedAssessment();
    $baseline = fingerprintOf($assessment);

    $assessment->status = AssessmentStatus::Draft;
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $assessment->status = AssessmentStatus::Computed;
    $assessment->sequence = 2;
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $assessment->sequence = 1;
    asDriverReturned($assessment, 'superseded_at', now()->addMinute());
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $assessment->superseded_at = null;
    $assessment->assessed_at = $assessment->assessed_at->copy()->addMinute();
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $assessment->assessed_at = $assessment->assessed_at->copy()->subMinute();
    $assessment->source_snapshot = ['policy' => 'changed'];
    expect(fingerprintOf($assessment))->not->toBe($baseline);

    $assessment->source_snapshot = [
        'policy' => 'fingerprint canonicalization fixture',
        'tracking_reference' => 'PVA-0012345',
        'or_number' => '0000123',
    ];
    $assessment->lines->first()->category = FeeRuleCategory::Tax;
    expect(fingerprintOf($assessment))->not->toBe($baseline);
});

it('produces the same fingerprint for a created assessment and its database reread', function () {
    $assessment = fingerprintedAssessment();

    // Simulate the driver representation a freshly created Assessment carries
    // when total_amount_cents was assigned from a SUM() aggregate.
    asDriverReturned($assessment, 'total_amount_cents', (string) $assessment->total_amount_cents);
    $createdHash = fingerprintOf($assessment);

    $reread = Assessment::query()->whereKey($assessment->id)->firstOrFail();

    expect(fingerprintOf($reread))->toBe($createdHash);
});
