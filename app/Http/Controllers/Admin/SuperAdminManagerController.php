<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SuperAdminManagerController extends Controller
{
    private function assertMaster(): void
    {
        abort_unless(Auth::user()->isMasterSuperAdmin(), 403);
    }

    public function index()
    {
        $this->assertMaster();

        $admins = User::where('role', 'super_admin')
            ->orderByRaw('sidebar_permissions IS NOT NULL')   // masters first
            ->orderBy('name')
            ->get();

        return view('admin.super-admins.index', [
            'admins'      => $admins,
            'permissions' => config('super_admin_permissions'),
        ]);
    }

    public function create()
    {
        $this->assertMaster();

        return view('admin.super-admins.create', [
            'permissions' => config('super_admin_permissions'),
        ]);
    }

    public function store(Request $request)
    {
        $this->assertMaster();

        $allSlugs = array_keys(config('super_admin_permissions'));

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
            'permissions'     => ['nullable', 'array'],
            'permissions.*'   => ['string', Rule::in($allSlugs)],
        ]);

        $selected = $data['permissions'] ?? [];
        $isAll    = count($selected) === count($allSlugs);

        User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'password'            => $data['password'],
            'role'                => 'super_admin',
            'is_active'           => true,
            'sidebar_permissions' => $isAll ? null : (empty($selected) ? [] : $selected),
            // Master-created super admin skips verification (PROC-024).
            'email_verified_at'   => now(),
        ]);

        return redirect()->route('super_admin.super-admins.index')
            ->with('success', __('ui.super_admins_page.created'));
    }

    public function edit(User $superAdmin)
    {
        $this->assertMaster();
        abort_unless($superAdmin->isSuperAdmin(), 404);

        return view('admin.super-admins.edit', [
            'superAdmin'  => $superAdmin,
            'permissions' => config('super_admin_permissions'),
        ]);
    }

    public function update(Request $request, User $superAdmin)
    {
        $this->assertMaster();
        abort_unless($superAdmin->isSuperAdmin(), 404);

        $allSlugs = array_keys(config('super_admin_permissions'));

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($superAdmin->id)],
            'password'        => ['nullable', 'string', 'min:8', 'confirmed'],
            'permissions'     => ['nullable', 'array'],
            'permissions.*'   => ['string', Rule::in($allSlugs)],
        ]);

        $selected = $data['permissions'] ?? [];
        $isAll    = count($selected) === count($allSlugs);

        $update = [
            'name'                => $data['name'],
            'email'               => $data['email'],
            'sidebar_permissions' => $superAdmin->isMasterSuperAdmin()
                ? null   // never strip master's full access from here
                : ($isAll ? null : (empty($selected) ? [] : $selected)),
        ];

        if (!empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        $superAdmin->update($update);

        return redirect()->route('super_admin.super-admins.index')
            ->with('success', __('ui.super_admins_page.updated'));
    }

    public function destroy(User $superAdmin)
    {
        $this->assertMaster();
        abort_unless($superAdmin->isSuperAdmin(), 404);
        abort_if($superAdmin->id === Auth::id(), 403, 'Cannot delete your own account.');

        $superAdmin->delete();

        return redirect()->route('super_admin.super-admins.index')
            ->with('success', __('ui.super_admins_page.deleted'));
    }
}
