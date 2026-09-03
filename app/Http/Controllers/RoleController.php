<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()->with(['permissions', 'users'])->orderBy('id')->get(),
            'permissionGroups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, Role $role, AuditService $auditService): RedirectResponse
    {
        $data = $request->validate([
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($role->slug === 'administrator' && ! $request->boolean('is_active')) {
            return back()->with('error', 'The Administrator role cannot be deactivated.');
        }

        $oldValues = [
            'is_active' => $role->is_active,
            'permissions' => $role->permissions->pluck('slug')->values()->all(),
        ];

        $role->update(['is_active' => $request->boolean('is_active')]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $newValues = [
            'is_active' => $role->is_active,
            'permissions' => $role->fresh('permissions')->permissions->pluck('slug')->values()->all(),
        ];
        $auditService->record('permissions_updated', "Updated permissions for the {$role->name} role.", $role, $oldValues, $newValues);

        return back()->with('success', "{$role->name} permissions updated successfully.");
    }
}
