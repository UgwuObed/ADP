<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    /**
     * Get all audit logs 
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with(['user' => function($q) {
            $q->select('id', 'full_name', 'email', 'role_id')->with('role:id,name');
        }]);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }
        
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $logs = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'logs' => AuditLogResource::collection($logs),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get audit logs for a specific user
     */
    public function userLogs(int $userId, Request $request): JsonResponse
    {
        $query = AuditLog::with(['user' => function($q) {
            $q->select('id', 'full_name', 'email', 'role_id')->with('role:id,name');
        }])->where('user_id', $userId);

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'logs' => AuditLogResource::collection($logs),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get single audit log details
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::with(['user' => function($q) {
            $q->with('role');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'log' => new AuditLogResource($log),
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $query = AuditLog::query();

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        $stats = [
            'total_logs' => (clone $query)->count(),
            'by_severity' => [
                'info' => (clone $query)->where('severity', 'info')->count(),
                'warning' => (clone $query)->where('severity', 'warning')->count(),
                'critical' => (clone $query)->where('severity', 'critical')->count(),
            ],
            'by_action' => (clone $query)
                ->select('action', \DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn($item) => [
                    'action' => $item->action,
                    'count' => $item->count,
                ])
                ->toArray(),
            'by_user_type' => [
                'system_admin' => (clone $query)->where('user_type', 'system_admin')->count(),
                'system_manager' => (clone $query)->where('user_type', 'system_manager')->count(),
                'super_admin' => (clone $query)->where('user_type', 'super_admin')->count(),
                'admin' => (clone $query)->where('user_type', 'admin')->count(),
                'manager' => (clone $query)->where('user_type', 'manager')->count(),
                'distributor' => (clone $query)->where('user_type', 'distributor')->count(),
            ],
            'top_active_users' => (clone $query)
                ->whereNotNull('user_id')
                ->select('user_id', \DB::raw('COUNT(*) as activity_count'))
                ->groupBy('user_id')
                ->orderByDesc('activity_count')
                ->limit(10)
                ->get()
                ->load(['user' => function($q) {
                    $q->select('id', 'full_name', 'email', 'role_id')->with('role:id,name');
                }])
                ->map(fn($item) => [
                    'user_id' => $item->user_id,
                    'user_name' => $item->user?->full_name,
                    'user_email' => $item->user?->email,
                    'role' => $item->user?->role?->name,
                    'activity_count' => $item->activity_count,
                ])
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'stats' => $stats,
        ]);
    }

    /**
     * Get recent critical activities
     */
    public function critical(Request $request): JsonResponse
    {
        $logs = AuditLog::with(['user' => function($q) {
            $q->select('id', 'full_name', 'email', 'role_id')->with('role:id,name');
        }])
            ->where('severity', 'critical')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'logs' => AuditLogResource::collection($logs),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Export audit logs 
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = AuditLog::with(['user' => function($q) {
            $q->with('role');
        }]);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-logs-' . now()->format('Y-m-d-His') . '.csv"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'ID',
                'User',
                'User Email',
                'User Role',
                'User Type',
                'Action',
                'Description',
                'Entity Type',
                'Entity ID',
                'IP Address',
                'Severity',
                'Timestamp',
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->user?->full_name ?? 'System',
                    $log->user?->email ?? 'N/A',
                    $log->user?->role?->name ?? 'N/A', 
                    $log->user_type ?? 'N/A',
                    $log->action,
                    $log->description,
                    $log->entity_type ?? 'N/A',
                    $log->entity_id ?? 'N/A',
                    $log->ip_address,
                    $log->severity,
                    $log->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, headers: $headers);
    }
}