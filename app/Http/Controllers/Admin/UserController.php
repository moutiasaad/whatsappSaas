<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ImpersonationLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function actor(): User
    {
        return auth()->user();
    }

    private function tenantScopedUsers()
    {
        $query = User::query();

        if (!$this->actor()->isSuperAdmin()) {
            $query->where('tenant_id', $this->actor()->tenant_id);
        }

        return $query;
    }

    private function tenantScopedTeams()
    {
        $query = Team::query();

        if (!$this->actor()->isSuperAdmin()) {
            $query->where('tenant_id', $this->actor()->tenant_id);
        }

        return $query;
    }

    private function assertCanManageUser(User $user): void
    {
        $actor = $this->actor();

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($user->tenant_id !== $actor->tenant_id || $user->isSuperAdmin()) {
            abort(403, __('ui.controller_messages.unauthorized'));
        }
    }

    private function validateTeamIds(?array $teamIds, ?int $tenantId = null): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $teamsQuery = $this->tenantScopedTeams();
        if ($tenantId !== null) {
            $teamsQuery->where('tenant_id', $tenantId);
        }

        $validIds = $teamsQuery->whereIn('id', $teamIds)->pluck('id')->all();

        if (count($validIds) !== count($teamIds)) {
            abort(422, __('ui.controller_messages.invalid_selected_teams'));
        }

        return $validIds;
    }

    public function index(Request $request)
    {
        $query = $this->tenantScopedUsers()
            ->with(['teams' => fn ($q) => $q->select('teams.id', 'teams.name')])
            ->where('id', '!=', auth()->id())
            ->when($request->search, fn ($q, $s) =>
                $q->where(function ($inner) use ($s) {
                    $inner->where('name', 'like', "%$s%")
                        ->orWhere('email', 'like', "%$s%");
                })
            )
            ->when($request->role,             fn ($q, $r) => $q->where('role', $r))
            ->when($request->status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

        if ($request->expectsJson()) {
            $paginated = $query->paginate((int) ($request->integer('per_page') ?: 20));
            return response()->json($paginated->toArray())
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return view('admin.users.index');
    }

    public function create()
    {
        $tenants = $this->actor()->isSuperAdmin()
            ? \App\Models\Tenant::orderBy('name')->get()
            : collect();
        $teams = $this->tenantScopedTeams()->where('is_active', true)->orderBy('name')->get();
        $agents = $this->tenantScopedUsers()
            ->whereIn('role', ['agent', 'supervisor'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
        return view('admin.users.create', compact('teams', 'tenants', 'agents'));
    }

    public function store(Request $request)
    {
        $actor = $this->actor();

        app(\App\Services\Billing\TenantQuota::class)->assertCanCreateUser($actor);

        $isSuperAdmin = $actor->isSuperAdmin();

        $data = $request->validate([
            'name'     => 'required|string|max:150',
            'email'    => 'required|email|unique:users,email',
            'role'     => ['required', Rule::in($isSuperAdmin ? ['agent', 'supervisor', 'admin', 'super_admin'] : ['agent', 'supervisor', 'admin'])],
            'password' => 'nullable|string|min:8',
            'tenant_id'=> $isSuperAdmin
                ? ['nullable', 'required_if:role,admin', 'exists:tenants,id']
                : ['prohibited'],
            'teams'    => 'nullable|array',
            'teams.*'  => 'exists:teams,id',
        ]);

        $tenantId = $isSuperAdmin
            ? ($data['tenant_id'] ?? null)
            : $actor->tenant_id;

        if (!$isSuperAdmin && $tenantId === null) {
            abort(422, __('ui.controller_messages.tenant_admin_requires_tenant'));
        }

        if ($isSuperAdmin && $data['role'] !== 'super_admin' && $tenantId === null) {
            abort(422, __('ui.controller_messages.tenant_required_for_non_super_admin_users'));
        }

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'tenant_id' => $tenantId,
            'password'  => Hash::make($data['password'] ?? str()->random(16)),
            'is_active' => true,
            // Admin-created users skip verification: the admin vouches for the
            // address by inviting them. PROC-024 only applies to public /register.
            'email_verified_at' => now(),
        ]);

        if (!$user->isSuperAdmin() && !empty($data['teams'])) {
            $user->teams()->sync($this->validateTeamIds($data['teams'], $user->tenant_id));
        }

        AuditLog::record('user.created', $user);

        if ($request->expectsJson()) {
            return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role], 201);
        }

        return redirect()->route($this->actor()->routeNamePrefix() . '.users.index')
            ->with('success', __('ui.controller_messages.user_invited', ['name' => $user->name]));
    }

    public function edit(User $user)
    {
        $this->assertCanManageUser($user);

        $user->load('teams');

        $teams = Team::withCount('users')
            ->when(!$this->actor()->isSuperAdmin(), fn ($q) => $q->where('tenant_id', $this->actor()->tenant_id))
            ->when($this->actor()->isSuperAdmin() && $user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stats = [
            'active'       => \App\Models\Conversation::where('owner_agent_id', $user->id)->whereIn('state', ['claimed'])->count(),
            'closed_total' => \App\Models\Conversation::where('owner_agent_id', $user->id)->where('state', 'closed')->count(),
            'closed_month' => \App\Models\Conversation::where('owner_agent_id', $user->id)->where('state', 'closed')->whereMonth('closed_at', now()->month)->count(),
        ];

        return view('admin.users.edit', compact('user', 'teams', 'stats'));
    }

    public function update(Request $request, User $user)
    {
        $this->assertCanManageUser($user);
        $actor = $this->actor();
        $isSuperAdmin = $actor->isSuperAdmin();

        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'role'      => ['required', Rule::in($isSuperAdmin ? ['agent', 'supervisor', 'admin', 'super_admin'] : ['agent', 'supervisor', 'admin'])],
            'is_active' => 'boolean',
            'tenant_id' => [$isSuperAdmin ? 'nullable' : 'prohibited', 'nullable', 'exists:tenants,id'],
            'teams'     => 'nullable|array',
            'teams.*'   => 'exists:teams,id',
        ]);

        $user->update([
            'name'      => $data['name'],
            'role'      => $data['role'],
            'is_active' => $data['is_active'] ?? false,
            'tenant_id' => $isSuperAdmin ? ($data['tenant_id'] ?? null) : $user->tenant_id,
        ]);

        if ($user->isSuperAdmin()) {
            $user->teams()->sync([]);
        } else {
            $user->teams()->sync($this->validateTeamIds($data['teams'] ?? [], $user->tenant_id));
        }

        AuditLog::record('user.updated', $user);

        return redirect()->route($this->actor()->routeNamePrefix() . '.users.index')
            ->with('success', __('ui.controller_messages.user_updated'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->assertCanManageUser($user);

        AuditLog::record('user.deleted', $user, ['name' => $user->name, 'email' => $user->email]);
        $user->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'User deleted.']);
        }

        return redirect()->route($this->actor()->routeNamePrefix() . '.users.index')
            ->with('success', __('ui.controller_messages.user_deleted', ['name' => $user->name]));
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'ids' => 'required|string',
        ]);

        $ids = collect(explode(',', $data['ids']))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.controller_messages.no_users_selected')], 422);
            }
            return back()->with('error', __('ui.controller_messages.no_users_selected'));
        }

        $action = $data['action'];
        $users = $this->tenantScopedUsers()
            ->whereIn('id', $ids)
            ->where('id', '!=', $this->actor()->id)
            ->get();

        if ($users->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.controller_messages.no_valid_users_selected')], 422);
            }
            return back()->with('error', __('ui.controller_messages.no_valid_users_selected'));
        }

        foreach ($users as $user) {
            if ($action === 'activate') {
                $user->update(['is_active' => true]);
                continue;
            }

            if ($action === 'deactivate') {
                $user->update(['is_active' => false]);
                continue;
            }

            AuditLog::record('user.deleted', $user, [
                'name' => $user->name,
                'email' => $user->email,
                'bulk' => true,
            ]);
            $user->delete();
        }

        $message = $action === 'delete'
            ? __('ui.controller_messages.users_deleted', ['count' => $users->count()])
            : __('ui.controller_messages.users_updated', ['count' => $users->count()]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function impersonate(User $user)
    {
        $actor = $this->actor();

        if (!$actor->isSuperAdmin() && (string) $actor->tenant_id !== (string) $user->tenant_id) {
            abort(403, __('ui.controller_messages.tenant_admin_impersonation_restricted'));
        }

        ImpersonationLog::create([
            'impersonator_user_id'  => auth()->id(),
            'impersonated_user_id'  => $user->id,
            'tenant_id'             => $user->tenant_id,
            'started_at'            => now(),
            'ip_address'            => request()->ip(),
        ]);

        AuditLog::record('user.impersonated', $user);

        session(['impersonating' => auth()->id()]);
        auth()->login($user);

        return redirect()->route($user->homeRouteName());
    }

    public function leaveImpersonation()
    {
        $originalId = session()->pull('impersonating');

        ImpersonationLog::where('impersonator_user_id', $originalId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first()?->update(['ended_at' => now()]);

        auth()->loginUsingId($originalId);

        return redirect()->route(auth()->user()->homeRouteName());
    }
}
