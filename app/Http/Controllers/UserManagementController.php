<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLog;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->user()?->role !== 'admin') {
            abort(403);
        }

        return Inertia::render('UserManagement', [
            'users' => User::orderBy('name')->get([
                'id', 'name', 'email', 'role', 'contact_number', 'is_active', 'created_at',
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            abort(403);
        }

        if ($request->has('contact_number')) {
            $request->merge(['contact_number' => PhilippineMobileNumber::normalize($request->input('contact_number'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,faculty,student'],
            'contact_number' => ['nullable', 'string', 'max:20', new PhilippineMobileNumber],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'contact_number' => $data['contact_number'] ?? null,
            'is_active' => true,
        ]);

        UserLog::record('Created user', "{$user->name} ({$user->role})");

        return response()->json(['success' => true, 'user' => $user->only(['id', 'name', 'email', 'role', 'contact_number', 'is_active', 'created_at'])]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            abort(403);
        }

        if ($request->has('contact_number')) {
            $request->merge(['contact_number' => PhilippineMobileNumber::normalize($request->input('contact_number'))]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,faculty,student'],
            'contact_number' => ['nullable', 'string', 'max:20', new PhilippineMobileNumber],
        ]);

        $user->update($data);

        UserLog::record('Updated user', "{$user->name} ({$user->role})");

        return response()->json(['success' => true, 'user' => $user->fresh()->only(['id', 'name', 'email', 'role', 'contact_number', 'is_active', 'created_at'])]);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            abort(403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot deactivate yourself.'], 422);
        }

        $user->update(['is_active' => false]);

        UserLog::record('Deactivated user', $user->name);

        return response()->json(['success' => true]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            abort(403);
        }

        $user->update(['is_active' => true]);

        UserLog::record('Activated user', $user->name);

        return response()->json(['success' => true]);
    }
}
