<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = AuditLog::with('user');

        if (!$request->user()->isSuperAdmin()) {
            $baseQuery->where('tenant_id', $request->user()->tenant_id);
        }

        $logs = (clone $baseQuery)
            ->when($request->search, fn ($q, $s) =>
                $q->where(function ($inner) use ($s) {
                    $inner->where('action', 'like', "%$s%")
                        ->orWhere('target_type', 'like', "%$s%");
                })
            )
            ->when($request->user_id, fn ($q, $uid) => $q->where('user_id', $uid))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to,   fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $users = User::orderBy('name')->get();

        if ($request->boolean('export')) {
            return $this->export($request);
        }

        $users = $request->user()->isSuperAdmin()
            ? User::orderBy('name')->get()
            : User::where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get();

        return view('admin.audit-log.index', compact('logs', 'users'));
    }

    private function export(Request $request)
    {
        $query = AuditLog::with('user');
        if (!$request->user()->isSuperAdmin()) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }

        $logs = $query
            ->when($request->user_id, fn ($q, $uid) => $q->where('user_id', $uid))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to,   fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')
            ->get();

        $csv  = "Date,User,Action,Target Type,Target ID,IP\n";
        foreach ($logs as $log) {
            $csv .= implode(',', [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user?->name ?? 'System',
                $log->action,
                $log->target_type ?? '',
                $log->target_id ?? '',
                $log->ip ?? '',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
