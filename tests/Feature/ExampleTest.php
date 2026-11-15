<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});

test('trusts proxy headers for https redirects', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'dashboard.mycosense.online',
    ])->get('/');

    $response->assertRedirect('https://dashboard.mycosense.online/dashboard');
});
