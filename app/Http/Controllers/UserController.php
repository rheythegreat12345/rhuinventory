<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $users = User::query()->with('role')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where(function ($nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('job_title', 'like', "%{$search}%");
            }))
            ->when($request->integer('role'), fn ($query, $role) => $query->where('role_id', $role))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Role::query()->orderBy('name')->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('users.form', ['managedUser' => new User(['status' => 'active']), 'roles' => Role::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request, AuditService $auditService): RedirectResponse
    {
        $user = User::query()->create($request->validated());
        $auditService->record('user_created', "Created user account for {$user->name}.", $user, null, Arr::except($user->toArray(), ['password']));

        return redirect()->route('users.show', $user)->with('success', 'User account created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): View
    {
        $user->load('role.permissions');

        return view('users.show', [
            'managedUser' => $user,
            'audits' => AuditLog::query()->where('user_id', $user->id)->latest()->limit(30)->get(),
            'transactions' => $user->transactions()->with(['medicine', 'batch'])->latest('transacted_at')->limit(15)->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        return view('users.form', ['managedUser' => $user, 'roles' => Role::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user, AuditService $auditService): RedirectResponse
    {
        $data = $request->validated();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->user()->is($user) && ($data['status'] !== 'active' || (int) $data['role_id'] !== (int) $user->role_id)) {
            return back()->with('error', 'You cannot deactivate your own account or change your own role.');
        }

        $oldValues = Arr::except($user->toArray(), ['password']);
        $user->update($data);
        $auditService->record('user_updated', "Updated user account for {$user->name}.", $user, $oldValues, Arr::except($user->fresh()->toArray(), ['password']));

        return redirect()->route('users.show', $user)->with('success', 'User account updated successfully.');
    }

    /**
     * Approve a pending user account.
     */
    public function approve(User $user, AuditService $auditService): RedirectResponse
    {
        if ($user->status === 'active') {
            return redirect()->route('users.show', $user)->with('success', 'User account is already active.');
        }

        $user->update(['status' => 'active']);
        $auditService->record('user_approved', "Approved user account for {$user->name}.", $user, ['status' => 'inactive'], ['status' => 'active']);

        return redirect()->route('users.show', $user)->with('success', 'User account approved. The staff member can now sign in.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user, Request $request, AuditService $auditService): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $isLastActiveAdministrator = $user->status === 'active'
            && $user->role?->slug === 'administrator'
            && User::query()->where('status', 'active')->whereHas('role', fn ($query) => $query->where('slug', 'administrator'))->count() === 1;

        if ($isLastActiveAdministrator) {
            return back()->with('error', 'You cannot delete the last active Administrator account.');
        }

        $oldValues = Arr::except($user->toArray(), ['password']);

        DB::transaction(function () use ($auditService, $oldValues, $user): void {
            $auditService->record('user_deleted', "Deleted user account for {$user->name}.", $user, $oldValues);
            $user->delete();
        });

        return redirect()->route('users.index')->with('success', 'User account deleted.');
    }
}
