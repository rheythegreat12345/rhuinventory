<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditService $auditService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The email, password, or account status is invalid.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (! $request->user()->isActive()) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account or its assigned role is inactive.']);
        }

        $request->user()->update(['last_login_at' => now()]);
        $auditService->record('login', 'Signed in to the inventory system.', $request->user());

        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back, '.$request->user()->name.'.');
    }

    public function destroy(Request $request, AuditService $auditService): RedirectResponse
    {
        $auditService->record('logout', 'Signed out of the inventory system.', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out securely.');
    }
}
