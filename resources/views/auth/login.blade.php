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

<p class="auth-footer">Don't have an account? <a class="link" href="{{ route('register') }}" data-auth-switch>Create account</a></p>
<p class="auth-disclaimer">Protected by role-based access, secure sessions, and activity logging.</p>
@endsection
