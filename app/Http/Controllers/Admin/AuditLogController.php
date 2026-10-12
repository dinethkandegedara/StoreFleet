<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of system audit logs.
     */
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user:id,name,email')
            ->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        $logs = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
            'filters' => [
                'search' => $request->input('search'),
                'event' => $request->input('event'),
            ],
            'availableEvents' => AuditLog::select('event')->distinct()->pluck('event'),
        ]);
    }
}
