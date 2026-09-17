<?php

test('staff navigation makes Inbox primary and keeps Applications as lookup', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $dashboard = file_get_contents(resource_path('js/pages/Dashboard.vue'));

    expect($sidebar)->toContain("title: 'My Work'")
        ->and(substr($sidebar, strpos($sidebar, "title: 'My Work'"), 180))
        ->toContain("title: 'Inbox'")
        ->and($dashboard)->toContain("title: 'Inbox / My Work'")
        ->toContain("description: 'Find or inspect an application record.'")
        ->not->toContain("title: 'Treasury Work'")
        ->toContain('href: reportCatalogIndex()');
});

test('inbox renders canonical task labels without changing action destinations', function () {
    $inbox = file_get_contents(resource_path('js/pages/work-inbox/Index.vue'));

    expect($inbox)->toContain('{{ item.task_label }}')
        ->toContain('item.action_url')
        ->toContain('No applications require action for this position.')
        ->toContain('No active municipal position');
});

test('preview launcher remains separately named and the ordinary home remains the home route', function () {
    $routes = file_get_contents(base_path('routes/web.php'));

    expect($routes)->toContain("Route::inertia('/', 'Welcome'")
        ->toContain("Route::get('stakeholder-preview'")
        ->toContain("->name('stakeholder-preview.index')")
        ->not->toContain("Route::get('/', [StakeholderPreviewController::class, 'index'])");
});

test('focused task pages provide a return to My Work affordance', function () {
    $certification = file_get_contents(resource_path('js/pages/post-payment-certifications/Show.vue'));
    $permit = file_get_contents(resource_path('js/pages/ordinary-uat-permit/Show.vue'));

    expect($certification)->toContain('Back to My Work')->toContain('workIndex()')
        ->and($permit)->toContain('Back to My Work')->toContain('workIndex()');
});

test('citizen navigation remains distinct from municipal work navigation', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $dashboard = file_get_contents(resource_path('js/pages/Dashboard.vue'));

    expect($sidebar)->toContain("title: 'My Businesses'")
        ->toContain("title: 'My Permit Applications'")
        ->and($dashboard)->toContain("title: 'Start/Continue Application'")
        ->toContain("title: 'Track Municipal Processing'");
});
