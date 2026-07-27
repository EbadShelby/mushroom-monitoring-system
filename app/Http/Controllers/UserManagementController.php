<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(): Response
    {
        if (auth()->user()?->role !== 'admin') {
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
        if (auth()->user()?->role !== 'admin') {
            abort(403);
        }

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

    public function update(Request $request, User $user): JsonResponse
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,faculty,student'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($data);

        return response()->json(['success' => true, 'user' => $user->fresh(['id', 'name', 'email', 'role', 'contact_number', 'is_active', 'created_at'])]);
    }

    public function deactivate(User $user): JsonResponse
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403);
        }

        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Cannot deactivate yourself.'], 422);
        }

        $user->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }

    public function activate(User $user): JsonResponse
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403);
        }

        $user->update(['is_active' => true]);

        return response()->json(['success' => true]);
    }
}
