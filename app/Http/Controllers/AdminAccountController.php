<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminAccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.accounts', [
            'pendingUsers' => User::query()
                ->with('role')
                ->where('status', 'inactive')
                ->latest()
                ->get(),
            'staffUsers' => User::query()
                ->with('role')
                ->whereKeyNot($request->user()->id)
                ->whereHas('role', fn ($query) => $query->where('slug', '!=', 'administrator'))
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function resetPassword(Request $request, User $user, AuditService $auditService): RedirectResponse
    {
        abort_if($user->role?->slug === 'administrator', 403, 'Administrator passwords can only be changed from their own profile.');

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        $auditService->record('admin_password_reset', "Reset password for {$user->name}.", $user);

        return back()->with('success', "Password reset for {$user->name}.");
    }
}
