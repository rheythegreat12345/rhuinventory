<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountApprovalNotificationService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private AccountApprovalNotificationService $accountApprovalNotificationService,
    ) {}

    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'terms' => ['accepted'],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $otp = $this->otpService->generate($request->email);
            $this->otpService->sendOtp($request->email, $otp);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['email' => 'The verification email could not be delivered. Please check the mail configuration and try again.']);
        }

        // Store registration data in session for OTP verification
        session([
            'registration_data' => [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'job_title' => $request->job_title,
            ],
        ]);

        return redirect()->route('register.otp')
            ->with('success', 'We\'ve sent a verification code to your email.');
    }

    public function showOtpForm(): View
    {
        if (! session('registration_data')) {
            return redirect()->route('register')->withErrors(['email' => 'Registration session expired. Please try again.']);
        }

        return view('auth.register-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'min:6', 'max:6'],
        ]);

        $registrationData = session('registration_data');

        if (! $registrationData) {
            return redirect()->route('register')->withErrors(['email' => 'Registration session expired. Please try again.']);
        }

        if (User::query()->where('email', $registrationData['email'])->exists()) {
            session()->forget('registration_data');

            return redirect()->route('register')->withErrors(['email' => 'An account with this email address already exists. Please sign in instead.']);
        }

        $defaultRole = Role::query()->where('slug', 'rhu-staff')->where('is_active', true)->first();

        if (! $defaultRole) {
            return back()->withErrors(['otp' => 'Account requests are temporarily unavailable. Please contact the system administrator.']);
        }

        // Verify OTP
        if (! $this->otpService->verify($registrationData['email'], $request->otp)) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please try again.']);
        }

        // Create user
        $user = User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'password' => $registrationData['password'],
            'role_id' => $defaultRole?->id,
            'status' => 'inactive',
            'phone' => $registrationData['phone'] ?? null,
            'job_title' => $registrationData['job_title'] ?? null,
            'email_verified_at' => now(),
        ]);

        $this->accountApprovalNotificationService->notifyAdministrators($user);

        // Clear registration session
        session()->forget('registration_data');

        return redirect()->route('login')->with('success', 'Your account request was submitted. An administrator must approve it before you can sign in.');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $registrationData = session('registration_data');

        if (! $registrationData) {
            return redirect()->route('register')->withErrors(['email' => 'Registration session expired. Please try again.']);
        }

        if (! $this->otpService->canResend($registrationData['email'])) {
            return back()->withErrors(['otp' => 'Please wait one minute before requesting another code.']);
        }

        try {
            $otp = $this->otpService->generate($registrationData['email']);
            $this->otpService->sendOtp($registrationData['email'], $otp);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['resend' => 'The verification email could not be delivered. Please check the mail configuration and try again.']);
        }

        return back()->with('success', 'A new verification code has been sent to your email.');
    }
}
