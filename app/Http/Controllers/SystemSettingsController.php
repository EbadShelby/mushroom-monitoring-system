<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(): Response
    {
        if (auth()->user()?->role !== 'admin') {
            return Inertia::render('Dashboard');
        }

        return Inertia::render('SystemSettings', [
            'users' => Inertia::defer(fn () => User::orderBy('name')->get([
                'id', 'name', 'email', 'role', 'contact_number', 'is_active', 'created_at',
            ])),
            'settings' => Setting::all()->pluck('value', 'key'),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'threshold_temperature_min' => ['nullable', 'numeric'],
            'threshold_temperature_max' => ['nullable', 'numeric'],
            'threshold_humidity_low' => ['nullable', 'numeric'],
            'threshold_humidity_high' => ['nullable', 'numeric'],
            'threshold_co2_max' => ['nullable', 'integer'],
            'threshold_soil_warning' => ['nullable', 'integer'],
            'threshold_soil_critical' => ['nullable', 'integer'],
            'led_on_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'led_off_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'sms_recipients' => ['nullable', 'string'],
            'system_name' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                Setting::set($key, (string) $value);
            }
        }

        return response()->json(['success' => true]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,faculty,student'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'contact_number' => $data['contact_number'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'user' => $user->only(['id', 'name', 'email', 'role', 'contact_number', 'is_active', 'created_at'])]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,faculty,student'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($data);

        return response()->json(['success' => true, 'user' => $user->fresh(['id', 'name', 'email', 'role', 'contact_number', 'is_active'])]);
    }

    public function deactivateUser(User $user): JsonResponse
    {
        // Prevent self-deactivation
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Cannot deactivate yourself.'], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }

    public function activateUser(User $user): JsonResponse
    {
        $user->update(['is_active' => true]);

        return response()->json(['success' => true]);
    }
}
