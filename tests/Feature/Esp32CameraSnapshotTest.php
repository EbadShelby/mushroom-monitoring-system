<?php

use App\Http\Controllers\Api\CameraSnapshotController;
use App\Models\CameraSnapshot;
use App\Models\GrowingCycle;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function fakeJpegBytes(): string
{
    return "\xFF\xD8\xFF\xE0".str_repeat('a', 64)."\xFF\xD9";
}

function createActiveCycle(): GrowingCycle
{
    return GrowingCycle::create([
        'name' => 'Cycle 1',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
}

beforeEach(function () {
    config(['services.camera.token' => 'secret-token']);
    Storage::fake('public');

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->andReturn(['led' => 'off'])->byDefault();
    $this->app->instance(FirebaseService::class, $firebase);
});

test('esp32 cam can upload a snapshot that attaches to the active cycle', function () {
    $cycle = createActiveCycle();

    $response = $this->call(
        'POST',
        '/api/camera-snapshot',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'image/jpeg', 'HTTP_X_CAMERA_TOKEN' => 'secret-token', 'HTTP_ACCEPT' => 'application/json'],
        fakeJpegBytes(),
    );

    $response->assertCreated()->assertJsonPath('success', true);

    $snapshot = CameraSnapshot::first();
    expect($snapshot->growing_cycle_id)->toBe($cycle->id)
        ->and($snapshot->uploaded_by)->toBeNull();
    Storage::disk('public')->assertExists($snapshot->file_path);
});

test('snapshot upload is rejected with a missing or wrong token', function (?string $token) {
    createActiveCycle();

    $server = ['CONTENT_TYPE' => 'image/jpeg', 'HTTP_ACCEPT' => 'application/json'];

    if ($token !== null) {
        $server['HTTP_X_CAMERA_TOKEN'] = $token;
    }

    $this->call('POST', '/api/camera-snapshot', [], [], [], $server, fakeJpegBytes())
        ->assertUnauthorized();

    expect(CameraSnapshot::count())->toBe(0);
})->with([null, 'wrong-token']);

test('snapshot upload is rejected when body is not a jpeg', function () {
    createActiveCycle();

    $this->call('POST', '/api/camera-snapshot', [], [], [], [
        'CONTENT_TYPE' => 'image/jpeg',
        'HTTP_X_CAMERA_TOKEN' => 'secret-token',
        'HTTP_ACCEPT' => 'application/json',
    ], 'not-an-image')->assertStatus(422);
});

test('snapshot upload succeeds without an active cycle', function () {
    $this->call('POST', '/api/camera-snapshot', [], [], [], [
        'CONTENT_TYPE' => 'image/jpeg',
        'HTTP_X_CAMERA_TOKEN' => 'secret-token',
        'HTTP_ACCEPT' => 'application/json',
    ], fakeJpegBytes())->assertCreated();

    $snapshot = CameraSnapshot::first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->growing_cycle_id)->toBeNull();
});

test('faculty can request a manual capture and the camera picks it up once', function () {
    createActiveCycle();
    $faculty = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($faculty)->postJson('/api/camera/capture')
        ->assertStatus(202)
        ->assertJsonPath('already_pending', false);

    $this->actingAs($faculty)->postJson('/api/camera/capture')
        ->assertStatus(202)
        ->assertJsonPath('already_pending', true);

    $headers = ['X-Camera-Token' => 'secret-token'];

    $this->getJson('/api/camera-command', $headers)->assertOk()->assertJsonPath('capture', true);
    $this->getJson('/api/camera-command', $headers)->assertOk()->assertJsonPath('capture', false);
});

test('manual capture is attributed to the user who requested it', function () {
    createActiveCycle();
    $faculty = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($faculty)->postJson('/api/camera/capture')->assertStatus(202);
    $this->getJson('/api/camera-command', ['X-Camera-Token' => 'secret-token'])->assertOk();

    $this->call('POST', '/api/camera-snapshot', [], [], [], [
        'CONTENT_TYPE' => 'image/jpeg',
        'HTTP_X_CAMERA_TOKEN' => 'secret-token',
        'HTTP_ACCEPT' => 'application/json',
    ], fakeJpegBytes())->assertCreated();

    $snapshot = CameraSnapshot::first();
    expect($snapshot->uploaded_by)->toBe($faculty->id)
        ->and($snapshot->notes)->toContain($faculty->name);
});

test('students cannot request a manual capture', function () {
    createActiveCycle();
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)->postJson('/api/camera/capture')->assertForbidden();
});

test('manual capture succeeds without an active growing cycle', function () {
    $faculty = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($faculty)->postJson('/api/camera/capture')->assertStatus(202);
});

test('camera command endpoint rejects a wrong token', function () {
    $this->getJson('/api/camera-command', ['X-Camera-Token' => 'wrong'])->assertUnauthorized();
});

test('scheduled capture saves as auto-captured without user attribution', function () {
    createActiveCycle();

    Cache::put(
        CameraSnapshotController::CAPTURE_REQUEST_KEY,
        ['scheduled' => true],
        now()->addMinutes(5),
    );

    $this->getJson('/api/camera-command', ['X-Camera-Token' => 'secret-token'])
        ->assertOk()
        ->assertJsonPath('capture', true);

    $this->call('POST', '/api/camera-snapshot', [], [], [], [
        'CONTENT_TYPE' => 'image/jpeg',
        'HTTP_X_CAMERA_TOKEN' => 'secret-token',
        'HTTP_ACCEPT' => 'application/json',
    ], fakeJpegBytes())->assertCreated();

    $snapshot = CameraSnapshot::first();
    expect($snapshot->uploaded_by)->toBeNull()
        ->and($snapshot->notes)->toBe('Auto-captured by ESP32-CAM');
});

test('camera command enables flash when grow led is off and disables it when led is on', function (string $ledState, bool $expectedFlash) {
    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->once()->andReturn(['led' => $ledState]);
    $this->app->instance(FirebaseService::class, $firebase);

    Cache::put(
        CameraSnapshotController::CAPTURE_REQUEST_KEY,
        ['scheduled' => true],
        now()->addMinutes(5),
    );

    $this->getJson('/api/camera-command', ['X-Camera-Token' => 'secret-token'])
        ->assertOk()
        ->assertJson([
            'capture' => true,
            'flash' => $expectedFlash,
        ]);
})->with([
    'led off' => ['off', true],
    'led on' => ['on', false],
]);

test('camera command falls back to LED schedule when firebase returns empty actuator state', function (int $hour, bool $expectedFlash) {
    $this->travelTo(now()->setTime($hour, 0));

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->once()->with(2)->andReturn([]);
    $this->app->instance(FirebaseService::class, $firebase);

    Cache::put(
        CameraSnapshotController::CAPTURE_REQUEST_KEY,
        ['scheduled' => true],
        now()->addMinutes(5),
    );

    $this->getJson('/api/camera-command', ['X-Camera-Token' => 'secret-token'])
        ->assertOk()
        ->assertJson([
            'capture' => true,
            'flash' => $expectedFlash,
        ]);
})->with([
    'daytime LED window' => [12, false],
    'nighttime LED window' => [22, true],
]);
