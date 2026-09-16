<?php

test('ordinary copy uses municipal terms and preserves domain distinctions', function () {
    $navigator = file_get_contents(resource_path('js/components/permit-applications/ApplicationDocumentNavigator.vue'));
    $application = file_get_contents(resource_path('js/components/permit-applications/ExecutableApplication.vue'));
    $routing = file_get_contents(resource_path('js/components/permit-applications/BploRoutingTaskSheet.vue'));

    expect($navigator)->toContain('Municipal Processing')->toContain('In progress')
        ->not->toContain('Living ·');
    expect($application)->toContain('Payment Schedule')->toContain('Assessment is not ready.')
        ->toContain('Official Receipts')->not->toContain('Schedule of Payment');
    expect($routing)->toContain('Confirm Treasury')->not->toContain('Confirm Treasury Classification');
});

test('public and historical boundaries remain explicit in ordinary copy', function () {
    $home = file_get_contents(resource_path('js/pages/Welcome.vue'));
    $verification = file_get_contents(resource_path('js/pages/public/PermitVerification.vue'));

    expect($home)->toContain('UAT')->toContain('production legal effect')
        ->not->toMatch('/Lifecycle Laboratory|cleanroom/i');
    expect($verification)->toContain('production authority')->toContain('legal effect');
});
