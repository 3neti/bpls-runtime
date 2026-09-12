<?php

use Symfony\Component\Process\Process;

it('submits the real historical Vue form through the Inertia request boundary', function () {
    $this->artisan('wayfinder:generate', ['--no-interaction' => true])->assertSuccessful();

    $process = new Process(['node', 'tests/Frontend/IpilHistoricalSearchForm.browser.mjs'], base_path());
    $process->setTimeout(90)->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
    expect(json_decode(trim($process->getOutput()), true)['status'])->toBe('passed');
})->skip(fn (): bool => getenv('IPIL_FORM_BROWSER_TESTS') !== '1', 'Opt in with IPIL_FORM_BROWSER_TESTS=1; requires the installed Playwright browser.');
