<?php

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\MushroomMeasurement;
use App\Models\SensorReading;
use App\Models\User;

test('authorized user can create growing cycle with single common substrate', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Sawdust',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('cycle.substrate_type', 'Sawdust');

    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Sawdust',
        'substrate_type' => 'Sawdust',
    ]);
});

test('authorized user can create growing cycle with multiple common substrates', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Mixed',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust, Rice Straw, Corn Cobs',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('cycle.substrate_type', 'Sawdust, Rice Straw, Corn Cobs');

    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Mixed',
        'substrate_type' => 'Sawdust, Rice Straw, Corn Cobs',
    ]);
});

test('authorized user can create growing cycle with custom substrate', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Custom',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Cotton Waste',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Custom',
        'substrate_type' => 'Cotton Waste',
    ]);
});

test('authorized user can create growing cycle with combined common and custom substrate', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Enriched',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust, Rice Bran (15%), Lime (1%)',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Enriched',
        'substrate_type' => 'Sawdust, Rice Bran (15%), Lime (1%)',
    ]);
});

test('substrate type is required when creating growing cycle', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-NoSubstrate',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => '',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('substrate_type');
});

test('student cannot create a growing cycle', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)->postJson('/api/cycles', [
        'name' => 'Batch Student',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertStatus(403);
});

test('cannot create a new growing cycle while an active cycle exists', function () {
    $user = User::factory()->create(['role' => 'admin']);

    GrowingCycle::create([
        'name' => 'Active Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Second Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('growing_cycles', [
        'name' => 'Second Batch',
    ]);
});

test('can create a new growing cycle when previous cycles are completed or archived', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    GrowingCycle::create([
        'name' => 'Completed Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => 'completed',
    ]);

    GrowingCycle::create([
        'name' => 'Archived Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(5)->toDateString(),
        'status' => 'archived',
    ]);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'New Active Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'New Active Batch',
        'status' => 'active',
    ]);
});

test('cannot reactivate a cycle while another cycle is active', function () {
    $user = User::factory()->create(['role' => 'admin']);

    GrowingCycle::create([
        'name' => 'Active Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $completed = GrowingCycle::create([
        'name' => 'Completed Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(10)->toDateString(),
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->putJson("/api/cycles/{$completed->id}", [
        'status' => 'active',
    ]);

    $response->assertStatus(422);
    expect($completed->fresh()->status)->toBe('completed');
});

test('growing cycle dayCount starts at day 1 and increments daily', function () {
    $todayCycle = GrowingCycle::create([
        'name' => 'Today Cycle',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $yesterdayCycle = GrowingCycle::create([
        'name' => 'Yesterday Cycle',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDay()->toDateString(),
        'status' => 'completed',
        'end_date' => now()->toDateString(),
    ]);

    $futureCycle = GrowingCycle::create([
        'name' => 'Future Cycle',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->addDay()->toDateString(),
        'status' => 'completed',
    ]);

    expect($todayCycle->dayCount())->toBe(1)
        ->and($yesterdayCycle->dayCount())->toBe(2)
        ->and($futureCycle->dayCount())->toBe(1);
});

test('authorized user can create growing cycle with multiple fruiting bag types and counts', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Mixed-Bags',
        'fruiting_bags' => [
            ['variety' => 'Gray Oyster Mushroom', 'count' => 10],
            ['variety' => 'White Oyster Mushroom', 'count' => 1],
        ],
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('cycle.mushroom_variety', '10× Gray Oyster Mushroom, 1× White Oyster Mushroom')
        ->assertJsonPath('cycle.fruiting_bags.0.count', 10)
        ->assertJsonPath('cycle.fruiting_bags.1.count', 1);

    $cycle = GrowingCycle::first();
    expect($cycle->fruiting_bags)->toHaveCount(2);
});

test('authorized user can rename a growing cycle and update fruiting bags', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $cycle = GrowingCycle::create([
        'name' => 'Gray Oyster Batch 1',
        'mushroom_variety' => '10× Gray Oyster Mushroom',
        'fruiting_bags' => [['variety' => 'Gray Oyster Mushroom', 'count' => 10]],
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->putJson("/api/cycles/{$cycle->id}", [
        'name' => 'White Oyster Batch 1',
        'fruiting_bags' => [
            ['variety' => 'White Oyster Mushroom', 'count' => 10],
        ],
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('cycle.name', 'White Oyster Batch 1')
        ->assertJsonPath('cycle.mushroom_variety', '10× White Oyster Mushroom');

    $cycle->refresh();
    expect($cycle->name)->toBe('White Oyster Batch 1')
        ->and($cycle->mushroom_variety)->toBe('10× White Oyster Mushroom')
        ->and($cycle->fruiting_bags)->toBe([['variety' => 'White Oyster Mushroom', 'count' => 10]]);
});

test('breach summary counts at most one breach per rolling 15-minute cooldown per sensor', function () {
    $cycle = GrowingCycle::create([
        'name' => 'Breach Cooldown Test',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $base = now()->startOfMinute();

    // Breach #1 at 00:00, still out of range at 00:05 and 00:14 -> stays 1 breach
    foreach ([0, 5, 14] as $min) {
        SensorReading::create([
            'growing_cycle_id' => $cycle->id,
            'temperature' => 28.0,
            'humidity' => 65.0,
            'co2_raw' => 600,
            'light_level' => 300,
            'soil_moisture' => 65,
            'soil_status' => 'moist',
            'recorded_at' => $base->copy()->addMinutes($min),
        ]);
    }

    // Still out of range at 00:15 (15 mins after 00:00) -> counts as breach #2 (+1 soil breach)
    SensorReading::create([
        'growing_cycle_id' => $cycle->id,
        'temperature' => 28.0,
        'humidity' => 68.0,
        'co2_raw' => 600,
        'light_level' => 300,
        'soil_moisture' => 55,
        'soil_status' => 'dry',
        'recorded_at' => $base->copy()->addMinutes(15),
    ]);

    $summary = $cycle->breachSummary();

    expect($summary['humidity'])->toBe(2)
        ->and($summary['soil_moisture'])->toBe(1)
        ->and($summary['temperature'])->toBe(0)
        ->and($summary['total_readings'])->toBe(4);
});

test('breach summary ignores high humidity within 3 minutes after humidifier shuts off', function () {
    $cycle = GrowingCycle::create([
        'name' => 'Settling Mist Test',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $offAt = now()->startOfMinute();

    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'off',
        'trigger' => 'automatic',
        'triggered_by' => 'system',
        'triggered_at' => $offAt,
    ]);

    // 1 minute after humidifier OFF -> residual mist settling, should NOT count
    SensorReading::create([
        'growing_cycle_id' => $cycle->id,
        'temperature' => 28.0,
        'humidity' => 96.5,
        'co2_raw' => 600,
        'light_level' => 300,
        'soil_moisture' => 50,
        'soil_status' => 'moist',
        'recorded_at' => $offAt->copy()->addMinute(),
    ]);

    // 5 minutes after humidifier OFF -> past 3-minute grace window, SHOULD count
    SensorReading::create([
        'growing_cycle_id' => $cycle->id,
        'temperature' => 28.0,
        'humidity' => 96.5,
        'co2_raw' => 600,
        'light_level' => 300,
        'soil_moisture' => 50,
        'soil_status' => 'moist',
        'recorded_at' => $offAt->copy()->addMinutes(5),
    ]);

    $summary = $cycle->breachSummary();

    expect($summary['humidity'])->toBe(1)
        ->and($summary['total_readings'])->toBe(2);
});

test('completing a growing cycle can optionally record harvest measurements in one step', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $cycle = GrowingCycle::create([
        'name' => 'Harvest Finish Batch',
        'mushroom_variety' => 'White Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(12)->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->postJson("/api/cycles/{$cycle->id}/end", [
            'fruiting_body_count' => 135,
            'height_cm' => 8.4,
            'cap_diameter_cm' => 6.1,
            'harvest_notes' => 'Clean first harvest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('cycle.status', 'completed');

    $measurement = MushroomMeasurement::where('growing_cycle_id', $cycle->id)->first();
    expect($measurement)->not->toBeNull()
        ->and($measurement->fruiting_body_count)->toBe(135)
        ->and((float) $measurement->height_cm)->toBe(8.4)
        ->and((float) $measurement->cap_diameter_cm)->toBe(6.1)
        ->and($measurement->notes)->toBe('Clean first harvest');
});

test('completing a growing cycle without harvest fields still completes cleanly', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $cycle = GrowingCycle::create([
        'name' => 'No Harvest Yet',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(10)->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->postJson("/api/cycles/{$cycle->id}/end")
        ->assertSuccessful()
        ->assertJsonPath('cycle.status', 'completed');

    expect(MushroomMeasurement::where('growing_cycle_id', $cycle->id)->count())->toBe(0);
});

test('authenticated user can compare growing cycles by averages, breaches, and harvest results', function () {
    $student = User::factory()->create(['role' => 'student']);

    $cycleA = GrowingCycle::create([
        'name' => 'Batch Alpha',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-05',
        'status' => 'completed',
    ]);

    $cycleB = GrowingCycle::create([
        'name' => 'Batch Beta',
        'mushroom_variety' => 'White Oyster Mushroom',
        'substrate_type' => 'Rice Straw',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-14',
        'status' => 'completed',
    ]);

    SensorReading::create([
        'growing_cycle_id' => $cycleA->id,
        'temperature' => 28.0,
        'humidity' => 84.0,
        'co2_raw' => 650,
        'light_level' => 300,
        'soil_moisture' => 68,
        'soil_status' => 'moist',
        'recorded_at' => '2026-10-02 10:00:00',
    ]);

    SensorReading::create([
        'growing_cycle_id' => $cycleB->id,
        'temperature' => 34.0,
        'humidity' => 70.0,
        'co2_raw' => 800,
        'light_level' => 280,
        'soil_moisture' => 62,
        'soil_status' => 'moist',
        'recorded_at' => '2026-10-11 10:00:00',
    ]);

    MushroomMeasurement::create([
        'growing_cycle_id' => $cycleA->id,
        'user_id' => $student->id,
        'observed_date' => '2026-10-05',
        'height_cm' => 9.5,
        'cap_diameter_cm' => 7.2,
        'fruiting_body_count' => 140,
    ]);

    $this->actingAs($student)
        ->getJson('/api/cycles/compare?ids[]='.$cycleA->id.'&ids[]='.$cycleB->id)
        ->assertSuccessful()
        ->assertJsonCount(2, 'cycles')
        ->assertJsonPath('cycles.0.name', 'Batch Alpha')
        ->assertJsonPath('cycles.0.averages.temperature', 28)
        ->assertJsonPath('cycles.0.averages.humidity', 84)
        ->assertJsonPath('cycles.0.breaches.total', 0)
        ->assertJsonPath('cycles.0.harvest.total_fruiting_bodies', 140)
        ->assertJsonPath('cycles.1.name', 'Batch Beta')
        ->assertJsonPath('cycles.1.breaches.temperature', 1)
        ->assertJsonPath('cycles.1.breaches.humidity', 1)
        ->assertJsonPath('cycles.1.breaches.total', 2)
        ->assertJsonPath('cycles.1.harvest.records_count', 0);
});

test('cycle compare requires at least two distinct valid cycle ids', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $cycle = GrowingCycle::create([
        'name' => 'Single Cycle',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => '2026-10-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->getJson('/api/cycles/compare?ids[]='.$cycle->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});
