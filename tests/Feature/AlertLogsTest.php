<?php

use App\Models\AlertLog;
use App\Models\User;

test('authenticated user can view alert logs page', function () {
    $user = User::factory()->create(['role' => 'admin']);

    AlertLog::create([
        'sensor' => 'temperature',
        'value_at_alert' => 35.5,
        'threshold_exceeded' => 'above 32°C',
        'recipient_number' => '09123456789',
        'message' => 'Too hot',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/alerts');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('AlertLogsView')
        ->has('logs.data', 1)
        ->where('logs.data.0.sensor', 'temperature')
        ->has('chartData', 1)
    );
});
