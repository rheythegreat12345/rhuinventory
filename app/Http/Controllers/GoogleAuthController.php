<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountApprovalNotificationService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private AccountApprovalNotificationService $accountApprovalNotificationService,
    ) {}

    public function redirect(): RedirectResponse
    {
        if (! filled(config('services.google.client_id'))
            || ! filled(config('services.google.client_secret'))
            || ! filled(config('services.google.redirect'))) {
            return redirect()->route('login')->with('error', 'Google sign-in is not configured. Please contact the system administrator.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = Str::lower(trim((string) $googleUser->getEmail()));

            if (! filled($email)) {
                return redirect()->route('login')->withErrors(['email' => 'Google did not provide a verified email address. Please try another account.']);
            }

            if (! $this->canUseGoogleSignIn($email)) {
                return redirect()->route('login')->with('error', 'This Google account is not authorized to access the RHU inventory system.');
            }

            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists, log them in
                if (! $user->isActive()) {
                    return redirect()->route('login')->withErrors(['email' => 'This account or its assigned role is inactive.']);
                }

                Auth::login($user, true);
                $request->session()->regenerate();
                $user->update(['last_login_at' => now()]);

                return redirect()->intended(route('dashboard'))->with('success', 'Welcome back, '.$user->name.'.');
            } else {
                // New user, generate OTP and redirect to OTP verification page
                $otp = $this->otpService->generate($email);
                $this->otpService->sendOtp($email, $otp);

                // Store Google user data in session for OTP verification
                session([
                    'google_user_data' => [
                        'name' => $googleUser->name,
                        'email' => $email,
                        'google_id' => $googleUser->id,
                        'avatar' => $googleUser->avatar,
                    ],
                ]);

                return redirect()->route('auth.google.otp');
            }
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication failed. Please try again.']);
        }
    }

    private function canUseGoogleSignIn(string $email): bool
    {
        $normalizedEmail = Str::lower($email);
        $allowedEmails = collect(config('services.google.allowed_emails', []))
            ->map(fn (string $allowedEmail): string => Str::lower($allowedEmail));

        if ($allowedEmails->contains($normalizedEmail)) {
            return true;
        }

        $domain = Str::after($normalizedEmail, '@');
        $allowedDomains = collect(config('services.google.allowed_domains', []))
            ->map(fn (string $allowedDomain): string => Str::lower($allowedDomain));

        return Str::contains($normalizedEmail, '@') && $allowedDomains->contains($domain);
    }

    public function showOtpForm(): View
    {
        if (! session('google_user_data')) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication session expired. Please try again.']);
        }

        return view('auth.google-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'min:6', 'max:6'],
        ]);

        $googleUserData = session('google_user_data');

        if (! $googleUserData) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication session expired. Please try again.']);
        }

        if (User::query()->where('email', $googleUserData['email'])->exists()) {
            session()->forget('google_user_data');

            return redirect()->route('login')->withErrors(['email' => 'An account with this email address already exists. Please sign in instead.']);
        }

        $defaultRole = Role::query()->where('slug', 'rhu-staff')->where('is_active', true)->first();

        if (! $defaultRole) {
            return back()->withErrors(['otp' => 'Account requests are temporarily unavailable. Please contact the system administrator.']);
        }

        // Verify OTP using the OTP service
        if (! $this->otpService->verify($googleUserData['email'], $request->otp)) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please try again.'])->withInput();
        }

        // Create new user
        $user = User::create([
            'name' => $googleUserData['name'],
            'email' => $googleUserData['email'],
            'password' => Hash::make(Str::random(16)), // Random password for Google users
            'role_id' => $defaultRole?->id,
            'status' => 'inactive',
            'phone' => null,
            'job_title' => null,
            'email_verified_at' => now(), // Google accounts are pre-verified
            'preferences' => [
                'google_id' => $googleUserData['google_id'],
                'avatar' => $googleUserData['avatar'],
                'auth_type' => 'google',
            ],
        ]);

        $this->accountApprovalNotificationService->notifyAdministrators($user);

        // Clear Google user data from session
        session()->forget('google_user_data');

        return redirect()->route('login')->with('success', 'Your account request was submitted. An administrator must approve it before you can sign in.');
    }
}
