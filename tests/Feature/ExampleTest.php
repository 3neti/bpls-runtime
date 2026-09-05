<?php

test('home follows the active environment safety boundary', function () {
    $response = $this->get(route('home'));

    if (config('stakeholder_preview.mode')) {
        $response->assertNotFound();

        return;
    }

    $response->assertOk();
});
