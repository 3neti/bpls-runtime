<?php

test('signature capture exposes a keyboard-reachable labelled control and modal actions', function () {
    $source = file_get_contents(resource_path('js/components/SignatureFacsimileCapture.vue'));

    expect($source)
        ->toContain('aria-labelledby="signature-capture-title"')
        ->toContain('aria-describedby="signature-capture-instructions"')
        ->toContain('tabindex="0"')
        ->toContain('aria-label="Signature drawing area"')
        ->toContain('Clear')
        ->toContain('Use signature');
});

test('disabled Treasury confirmation states its actionable prerequisite', function () {
    $source = file_get_contents(resource_path('js/components/permit-applications/BploRoutingTaskSheet.vue'));

    expect($source)
        ->toContain(':disabled="!treasurySelectionsReady"')
        ->toContain('data-testid="treasury-confirm-reason"')
        ->toContain('{{ treasuryConfirmReason }}');
});
