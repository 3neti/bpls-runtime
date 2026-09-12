<?php

use App\Models\IpilHistoricalMediaEvidence;
use App\Models\User;
use App\Support\IpilRescue\Gate6ExecutionAuthorization;
use App\Support\IpilRescue\Gate8cExecutionAuthorization;

test('Gate 8C refuses absent private authorization before connecting or writing', function () {
    expect(fn () => Gate8cExecutionAuthorization::issue('/missing/manifest.json', '/missing/authorization.json'))
        ->toThrow(RuntimeException::class, 'private local authorization');
});

test('Gate 8C refuses unbound or workflow authorization', function (array $record) {
    expect(fn () => Gate8cExecutionAuthorization::validateBindings($record, []))
        ->toThrow(RuntimeException::class, 'binding mismatch');
})->with([
    'empty' => [[]],
    'old gate' => [['schema_version' => 'bpls.ipil-gate6-execution-authorization.v1']],
    'workflow' => [['schema_version' => 'bpls.ipil-gate8c-execution-authorization.v1', 'authority' => 'explicit-owner-gate8c-directive', 'application_id' => 'app-a28928ff-2881-48eb-bfbb-f89cfac5f51d']],
]);

test('Gate 6 still refuses Cloud execution', function () {
    app()->instance('env', 'historical-uat');
    expect(fn () => Gate6ExecutionAuthorization::issue('/missing/manifest.json', 'private-historical-uat'))
        ->toThrow(RuntimeException::class, 'restricted to the explicit local-postgresql');
});

test('reviewer provisioning fails closed outside commissioned Cloud configuration', function () {
    $this->artisan('ipil:uat:reviewer')->assertFailed();
    expect(User::query()->count())->toBe(0);
});

test('historical media retains private local default and supports explicit isolated destination', function () {
    expect(Gate8cExecutionAuthorization::Bucket)->toBe('fls-a2b95366-1364-456a-8471-86c4feac19fc');
    $evidence = new IpilHistoricalMediaEvidence;
    $evidence->registerMediaCollections();
    expect($evidence->getRegisteredMediaCollections()->first()->diskName)->toBe('ipil_gate6');
    $remote = new IpilHistoricalMediaEvidence;
    $remote->historicalMediaDisk = 'ipil_gate8c';
    $remote->registerMediaCollections();
    expect($remote->getRegisteredMediaCollections()->first()->diskName)->toBe('ipil_gate8c')
        ->and(config('filesystems.disks.ipil_gate8c.visibility'))->toBe('private')
        ->and(config('filesystems.disks.ipil_gate8c.url'))->toBeNull();
});
