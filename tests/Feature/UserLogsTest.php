<?php

use App\Models\User;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth;

test('admin can view user logs page with recorded entries', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    UserLog::record('Updated system settings', 'system_name', $admin);

    $this->actingAs($admin)
        ->get('/user-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('UserLogsView')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'Updated system settings')
                ->where('logs.data.0.details', 'system_name')
            )
        );
});

test('login and logout events record user logs', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    Auth::login($user);
    Auth::logout();

    expect(UserLog::where('user_id', $user->id)->where('action', 'Logged in')->exists())->toBeTrue()
        ->and(UserLog::where('user_id', $user->id)->where('action', 'Logged out')->exists())->toBeTrue();
});

test('user actions record audit logs', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/api/cycles', [
        'name' => 'Audit Cycle',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ])->assertOk();

    $this->actingAs($admin)->putJson('/api/settings', [
        'system_name' => 'CotSU Lab',
    ])->assertOk();

    expect(UserLog::where('user_id', $admin->id)->where('action', 'Created growing cycle')->where('details', 'Audit Cycle')->exists())->toBeTrue()
        ->and(UserLog::where('user_id', $admin->id)->where('action', 'Updated system settings')->exists())->toBeTrue();
});
