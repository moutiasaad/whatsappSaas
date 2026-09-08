<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsAppInstance;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    private function ensureTeamAccess(Team $team): void
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && (int) $team->tenant_id !== (int) $user->tenant_id) {
            abort(403, __('ui.controller_messages.not_allowed_to_manage_team'));
        }

        if (!$user->isSupervisor()) {
            return;
        }

        $isMember = $team->users()->where('users.id', $user->id)->exists();
        abort_unless($isMember, 403, __('ui.controller_messages.not_allowed_to_manage_team'));
    }

    private function scopedUsers()
    {
        $query = User::whereIn('role', ['agent', 'supervisor'])->where('is_active', true);

        if (!auth()->user()->isSuperAdmin()) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        return $query;
    }

    private function validateMembers(?array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $validIds = $this->scopedUsers()->whereIn('id', $memberIds)->pluck('id')->all();

        if (count($validIds) !== count($memberIds)) {
            abort(422, __('ui.controller_messages.invalid_selected_team_members'));
        }

        return $validIds;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $baseQuery = Team::query()
            ->when($user->isSupervisor(), function ($query) use ($user) {
                $query->whereHas('users', fn ($inner) => $inner->where('users.id', $user->id));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->string('search'));
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->string('is_active')->value() === '1'))
            ->withCount([
                'users',
                'conversations as active_conversations_count' => fn ($q) => $q->whereIn('state', ['pool', 'claimed']),
                'conversations as pool_count' => fn ($q) => $q->where('state', 'pool'),
                'conversations as closed_count' => fn ($q) => $q->where('state', 'closed'),
            ]);

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'active'   => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
            'pool'     => (clone $baseQuery)->get()->sum('pool_count'),
        ];

        if ($request->expectsJson()) {
            $listQuery = clone $baseQuery;

            match ($request->string('sort')->value()) {
                'name_desc'     => $listQuery->orderByDesc('name'),
                'activity_desc' => $listQuery->orderByDesc('active_conversations_count')->orderByDesc('name'),
                'pool_desc'     => $listQuery->orderByDesc('pool_count')->orderByDesc('name'),
                default         => $listQuery->orderBy('name'),
            };

            $paginated = $listQuery->paginate((int) ($request->integer('per_page') ?: 18));

            return response()->json(
                array_merge($paginated->toArray(), ['stats' => $stats])
            )->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return view('admin.teams.index', [
            'canManageTeams' => $user->hasAnyRole(['admin', 'super_admin']),
            'isSupervisor'   => $user->isSupervisor(),
        ]);
    }

    public function create()
    {
        $agents = $this->scopedUsers()->orderBy('name')->get();
        return view('admin.teams.create', compact('agents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:250',
            'is_active'   => 'boolean',
            'members'     => 'nullable|array',
            'members.*'   => 'exists:users,id',
        ]);

        $team = Team::create([
            'tenant_id'   => auth()->user()->tenant_id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
        ]);

        if (!empty($data['members'])) {
            $team->users()->sync($this->validateMembers($data['members']));
        }

        AuditLog::record('team.created', $team);

        if ($request->expectsJson()) {
            return response()->json(['id' => $team->id, 'name' => $team->name], 201);
        }

        return redirect()->route(auth()->user()->routeNamePrefix() . '.teams.index')
            ->with('success', __('ui.controller_messages.team_created', ['name' => $team->name]));
    }

    public function edit(Team $team)
    {
        $this->ensureTeamAccess($team);

        $agents = $this->scopedUsers()->orderBy('name')->get();
        $team->load('users');

        $linkedInstances = WhatsAppInstance::where('team_id', $team->id)->orderBy('name')->get();

        $stats = [
            'pool'         => Conversation::where('team_id', $team->id)->where('state', 'pool')->count(),
            'claimed'      => Conversation::where('team_id', $team->id)->where('state', 'claimed')->count(),
            'closed_today' => Conversation::where('team_id', $team->id)->where('state', 'closed')->whereDate('closed_at', today())->count(),
            'avg_response' => null,
        ];

        return view('admin.teams.edit', compact('team', 'agents', 'linkedInstances', 'stats'));
    }

    public function update(Request $request, Team $team)
    {
        $this->ensureTeamAccess($team);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:250',
            'is_active'   => 'boolean',
            'members'     => 'nullable|array',
            'members.*'   => 'exists:users,id',
        ]);

        $team->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? false,
        ]);

        $team->users()->sync($this->validateMembers($data['members'] ?? []));

        AuditLog::record('team.updated', $team);

        return redirect()->route(auth()->user()->routeNamePrefix() . '.teams.index')
            ->with('success', __('ui.controller_messages.team_updated'));
    }

    public function destroy(Request $request, Team $team)
    {
        $this->ensureTeamAccess($team);

        AuditLog::record('team.deleted', $team, ['name' => $team->name]);
        $team->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Team deleted.']);
        }

        return redirect()->route(auth()->user()->routeNamePrefix() . '.teams.index')
            ->with('success', __('ui.controller_messages.team_deleted', ['name' => $team->name]));
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:enable,disable,delete',
            'ids' => 'required|string',
        ]);

        $ids = collect(explode(',', $data['ids']))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', __('ui.controller_messages.no_teams_selected'));
        }

        $query = Team::query()->whereIn('id', $ids);
        if (!auth()->user()->isSuperAdmin()) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        $teams = $query->get();

        if ($teams->isEmpty()) {
            return back()->with('error', __('ui.controller_messages.no_valid_teams_selected'));
        }

        if ($data['action'] !== 'delete') {
            $enable = $data['action'] === 'enable';
            Team::whereIn('id', $teams->pluck('id'))->update(['is_active' => $enable]);

            $message = $enable
                ? __('ui.controller_messages.teams_enabled', ['count' => $teams->count()])
                : __('ui.controller_messages.teams_disabled', ['count' => $teams->count()]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }

            return back()->with('success', $message);
        }

        foreach ($teams as $team) {
            AuditLog::record('team.deleted', $team, ['name' => $team->name, 'bulk' => true]);
            $team->delete();
        }

        $message = __('ui.controller_messages.teams_deleted', ['count' => $teams->count()]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
