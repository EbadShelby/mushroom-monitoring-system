<?php

use App\Models\AlertLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;

test('alerts go to user numbers and settings numbers without duplicates', function () {
    config(['services.semaphore.api_key' => 'test-key']);
    Http::fake();

    User::factory()->create(['role' => 'faculty', 'contact_number' => '+639123456789']);
    User::factory()->create(['role' => 'student', 'contact_number' => '09000000000']);
    Setting::set('sms_recipients', '09123456789,09987654321');

    app(SmsService::class)->sendAlert([
        'sensor' => 'temperature',
        'value' => 40,
        'threshold' => 30,
        'message' => 'Too hot',
    ]);

    expect(AlertLog::pluck('recipient_number')->sort()->values()->all())
        ->toBe(['09123456789', '09987654321']);
    Http::assertSentCount(2);
});

