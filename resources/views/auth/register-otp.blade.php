@extends('layouts.guest')
@section('title', 'Verify Email')
@section('content')
<div style="text-align: center; margin-bottom: 32px;">
    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="white">
            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
        </svg>
    </div>
    <h1>Verify Your Email</h1>
    <p style="color: var(--muted);">We've sent a 6-digit verification code to <strong>{{ session('registration_data.email') ?? 'your email' }}</strong>. Your request will be sent to an administrator for approval after verification.</p>
</div>

<form class="auth-form" data-auth-form method="POST" action="{{ route('register.verify-otp') }}">
    @csrf
    <div class="field">
        <label class="required" for="otp">Verification Code</label>
        <input class="input {{ $errors->has('otp') ? 'input-error' : '' }}" 
               id="otp" 
               name="otp" 
               type="text" 
               maxlength="6" 
               pattern="[0-9]{6}" 
               inputmode="numeric" 
               autocomplete="one-time-code" 
               required 
               placeholder="Enter 6-digit code"
               style="letter-spacing: 0.5em; text-align: center; font-size: 1.5em;">
        @error('otp')
            <p class="error-message">{{ $message }}</p>
        @enderror
    </div>

    <button class="btn btn-primary auth-submit" type="submit"><span>Verify & Submit Request</span><span class="button-spinner" aria-hidden="true"></span></button>
</form>

<div style="margin: 16px 0; text-align: center;">
    <form method="POST" action="{{ route('register.resend-otp') }}" style="display: inline;">
        @csrf
        <button type="submit" class="btn btn-secondary" style="height:36px;padding:0 16px;">
            Resend Code
        </button>
    </form>
    @error('resend')<p class="error-message">{{ $message }}</p>@enderror
</div>

<div style="margin-top: 20px; text-align: center;">
    <a class="link" href="{{ route('register') }}">Back to Registration</a>
</div>

<p class="auth-disclaimer">The code expires in 10 minutes. Keep it private and do not share it with anyone.</p>
@endsection
