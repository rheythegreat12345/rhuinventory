<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
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
        private OtpService $otpService
    ) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = $googleUser->getEmail();

            if (! filled($email)) {
                return redirect()->route('login')->withErrors(['email' => 'Google did not provide a verified email address. Please try another account.']);
            }

            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists, log them in
                if (! $user->isActive()) {
                    return redirect()->route('login')->withErrors(['email' => 'This account or its assigned role is inactive.']);
                }

                Auth::login($user, true);
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

        // Verify OTP using the OTP service
        if (! $this->otpService->verify($googleUserData['email'], $request->otp)) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please try again.'])->withInput();
        }

        // Create new user
        $defaultRole = Role::where('slug', 'staff')->first() ?? Role::first();

        $user = User::create([
            'name' => $googleUserData['name'],
            'email' => $googleUserData['email'],
            'password' => Hash::make(Str::random(16)), // Random password for Google users
            'role_id' => $defaultRole?->id,
            'status' => 'active',
            'phone' => null,
            'job_title' => null,
            'email_verified_at' => now(), // Google accounts are pre-verified
            'preferences' => [
                'google_id' => $googleUserData['google_id'],
                'avatar' => $googleUserData['avatar'],
                'auth_type' => 'google',
            ],
        ]);

        // Clear Google user data from session
        session()->forget('google_user_data');

        Auth::login($user, true);
        $user->update(['last_login_at' => now()]);

        return redirect()->route('dashboard')->with('success', 'Account created successfully! Welcome, '.$user->name.'.');
    }
}
