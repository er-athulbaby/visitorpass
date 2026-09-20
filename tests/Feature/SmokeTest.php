<?php

test('the application boots and serves the login page', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
