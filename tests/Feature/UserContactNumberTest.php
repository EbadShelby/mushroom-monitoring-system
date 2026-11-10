<?php

use App\Models\Setting;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;

dataset('contact number formats', [
    '09 local' => ['09123456789', '09123456789'],
    'plus 63' => ['+639123456789', '09123456789'],
    '63 prefix' => ['639123456789', '09123456789'],
    'spaced' => ['0912 345 6789', '09123456789'],
    'dashed' => ['0912-345-6789', '09123456789'],
]);

test('contact numbers are normalized when creating a user', function (string $input, string $stored) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/users', [
            'name' => 'Faculty One',
            'email' => 'faculty.one@example.com',
            'password' => 'password123',
            'role' => 'faculty',
            'contact_number' => $input,
        ])
        ->assertOk();

    expect(User::where('email', 'faculty.one@example.com')->value('contact_number'))->toBe($stored);
})->with('contact number formats');

test('invalid contact numbers are rejected', function (string $input) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/users', [
            'name' => 'Faculty One',
            'email' => 'faculty.one@example.com',
            'password' => 'password123',
            'role' => 'faculty',
            'contact_number' => $input,
        ])
        ->assertJsonValidationErrors('contact_number');
})->with(['0912345678', '08123456789', '+1 555 123 4567', 'abc']);

test('contact number is optional on create and untouched when omitted on update', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'faculty', 'contact_number' => '09123456789']);

    $this->actingAs($admin)
        ->putJson("/api/users/{$user->id}", ['name' => 'Renamed'])
        ->assertOk();

    expect($user->fresh()->contact_number)->toBe('09123456789');

    $this->actingAs($admin)
        ->putJson("/api/users/{$user->id}", ['contact_number' => '+639987654321'])
        ->assertOk();

    expect($user->fresh()->contact_number)->toBe('09987654321')
        ->and(PhilippineMobileNumber::normalize('  '))->toBeNull();
});

test('sms recipient settings are normalized and validated', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->putJson('/api/settings', ['sms_recipients' => '+639123456789, 09987654321 ,639111111111'])
        ->assertOk();

    expect(Setting::where('key', 'sms_recipients')->value('value'))
        ->toBe('09123456789,09987654321,09111111111');

    $this->actingAs($admin)
        ->putJson('/api/settings', ['sms_recipients' => '09123456789,0912'])
        ->assertJsonValidationErrors('sms_recipients');
});
