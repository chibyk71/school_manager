<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationLogController extends Controller
{
    // app/Http/Controllers/Admin/NotificationLogController.php
    public function index(Request $request)
    {
        $query = NotificationLog::with(['school', 'notifiable'])
            ->when($request->search, fn($q) => $q->where('recipient', 'like', "%{$request->search}%")
                ->orWhere('message', 'like', "%{$request->search}%"))
            ->when($request->channel, fn($q) => $q->where('channel', $request->channel))
            ->when($request->success !== null, fn($q) => $q->where('success', $request->success))
            ->when($request->provider, fn($q) => $q->where('provider', $request->provider))
            ->when($request->school_id, fn($q) => $q->where('school_id', $request->school_id))
            ->latest()
            ->paginate(50);

        return Inertia::render('/Notifications/History', [
            'logs' => $query,
            'filters' => $request->all(),
            'schools' => School::orderBy('name')->pluck('name', 'id')
        ]);
    }
}
