@extends('layouts.guest')
@section('title', 'Choose a new password')
@section('content')
<div class="auth-heading"><span class="auth-eyebrow">Account security</span><h1>Choose a new password</h1><p>Create a strong password that you do not use on other services.</p></div>
<form class="auth-form" data-auth-form method="POST" action="{{ route('password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label class="required" for="email">Email address</label><div class="input-wrap"><x-icon name="profile" /><input class="input {{ $errors->has('email') ? 'input-error' : '' }}" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required></div>@error('email')<p class="error-message">{{ $message }}</p>@enderror</div>
    <div class="field"><label class="required" for="password">New password</label><div class="input-wrap"><x-icon name="alert" /><input class="input {{ $errors->has('password') ? 'input-error' : '' }}" id="password" name="password" type="password" autocomplete="new-password" data-password-strength required><button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><x-icon name="eye" /></button></div><div class="password-strength" data-password-strength-status aria-live="polite"><span></span><span></span><span></span><span></span><small>Use 8+ characters, upper/lowercase, a number, and a symbol.</small></div>@error('password')<p class="error-message">{{ $message }}</p>@enderror</div>
    <div class="field"><label class="required" for="password_confirmation">Confirm new password</label><div class="input-wrap"><x-icon name="alert" /><input class="input {{ $errors->has('password_confirmation') ? 'input-error' : '' }}" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><x-icon name="eye" /></button></div>@error('password_confirmation')<p class="error-message">{{ $message }}</p>@enderror</div>
    <button class="btn btn-primary auth-submit" type="submit"><span>Reset password</span><span class="button-spinner" aria-hidden="true"></span></button>
</form>
@endsection
