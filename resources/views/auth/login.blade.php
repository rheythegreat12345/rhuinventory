@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<div class="auth-heading"><span class="auth-eyebrow">Welcome back</span><h1>Sign in to your workspace</h1><p>Manage the health unit's medicine inventory securely.</p></div>

<form class="auth-form" data-auth-form method="POST" action="{{ route('login.store') }}">@csrf
    <div class="field"><label class="required" for="email">Email address</label><div class="input-wrap"><x-icon name="mail" /><input class="input {{ $errors->has('email') ? 'input-error' : '' }}" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required placeholder="you@rhu.gov.ph" aria-describedby="email-error"></div>@error('email')<p class="error-message" id="email-error">{{ $message }}</p>@enderror</div>
    <div class="field"><label class="required" for="password">Password</label><div class="input-wrap"><x-icon name="lock" /><input class="input {{ $errors->has('password') ? 'input-error' : '' }}" id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" aria-describedby="password-error"><button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><x-icon name="eye" /></button></div>@error('password')<p class="error-message" id="password-error">{{ $message }}</p>@enderror</div>
    <div class="auth-row"><label class="checkbox-row"><input name="remember" type="checkbox" value="1" @checked(old('remember'))><span>Remember me</span></label><a class="link" href="{{ route('password.request') }}" data-auth-switch>Forgot password?</a></div>
    <button class="btn btn-primary auth-submit" type="submit"><span>Sign in securely</span><x-icon name="chevron" class="icon-sm" /><span class="button-spinner" aria-hidden="true"></span></button>
</form>

<div class="auth-divider"><span>or continue with</span></div>

<a href="{{ route('auth.google') }}" class="btn btn-secondary auth-social">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
    Continue with Google
</a>

<p class="auth-footer">Don't have an account? <a class="link" href="{{ route('register') }}" data-auth-switch>Create account</a></p>
<p class="auth-disclaimer">Protected by role-based access, secure sessions, and activity logging.</p>
@endsection
