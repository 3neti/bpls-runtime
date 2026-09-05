<?php

use App\Actions\ResolveOfficialReceiptProfile;

test('AF No. 51 receipt profile is explicit and configurable', function () {
    $profile = app(ResolveOfficialReceiptProfile::class)->handle();

    expect(data_get($profile, 'profile_key'))->toBe('ipil-af51-nelson-v1')
        ->and(data_get($profile, 'form.accountable_form_number'))->toBe(51)
        ->and(data_get($profile, 'form.copy_designation'))->toBe('ORIGINAL')
        ->and(data_get($profile, 'collecting_officer.name'))->toBe('MARIA LUZ F. PULMANO')
        ->and(collect(data_get($profile, 'payment_instruments'))->pluck('key')->all())
        ->toContain('cash', 'check', 'money_order', 'qr_ph')
        ->and(data_get($profile, 'laboratory_watermark'))->toContain('NOT FOR ACCOUNTING USE');
});

test('invalid AF No. 51 configuration is refused', function () {
    config()->set('municipality.official_receipt.form.accountable_form_number', 99);

    expect(fn () => app(ResolveOfficialReceiptProfile::class)->handle())
        ->toThrow(LogicException::class);
});
