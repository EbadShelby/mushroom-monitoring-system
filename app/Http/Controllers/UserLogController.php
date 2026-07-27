<?php

namespace App\Http\Controllers;

use App\Models\UserLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserLogController extends Controller
{
    public function index(Request $request): Response
    {
        // Admin-only guard
        if (auth()->user()?->role !== 'admin') {
            return Inertia::render('Dashboard');
        }

        return Inertia::render('UserLogsView', [
            'logs' => Inertia::defer(fn () => UserLog::query()
                ->with('user:id,name,email,role')
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
                ->when($request->filled('from'), fn ($q) => $q->where('performed_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->where('performed_at', '<=', $request->input('to').' 23:59:59'))
                ->orderByDesc('performed_at')
                ->paginate(25)
            ),
        ]);
    }
}
