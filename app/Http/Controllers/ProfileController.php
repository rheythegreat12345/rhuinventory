<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request, AuditService $auditService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($request->user())],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'theme' => ['required', 'in:light,dark,system'],
        ]);

        $oldValues = $request->user()->only(['name', 'email', 'phone', 'job_title']);
        $request->user()->update([
            ...collect($data)->except('theme')->all(),
            'preferences' => [...($request->user()->preferences ?? []), 'theme' => $data['theme']],
        ]);
        $auditService->record('profile_update', 'Updated personal profile.', $request->user(), $oldValues, $request->user()->fresh()->only(array_keys($oldValues)));

        return back()->with('success', 'Profile updated successfully.');
    }

    public function password(Request $request, AuditService $auditService): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $storedPassword = (string) $request->user()->getAuthPassword();
        $currentPasswordIsValid = password_get_info($storedPassword)['algo'] !== null
            ? Hash::check($data['current_password'], $storedPassword)
            : hash_equals($storedPassword, $data['current_password']);

        if (! $currentPasswordIsValid) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.password'),
            ]);
        }

        $request->user()->update(['password' => Hash::make($data['password'])]);
        $auditService->record('password_change', 'Changed account password.', $request->user());

        return back()->with('success', 'Password changed successfully.');
    }
}
