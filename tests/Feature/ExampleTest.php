<?php

test('ordinary home is available independently of preview readiness', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});
