@extends('layouts.guest')
@section('title', 'Request Account Access')
@section('content')
<div class="auth-heading"><span class="auth-eyebrow">Request access</span><h1>Request an account</h1><p>Verify your email, then wait for an administrator to approve your access.</p></div>

<form class="auth-form" data-auth-form method="POST" action="{{ route('register.store') }}">
    @csrf
    <div class="field"><label class="required" for="name">Full name</label><div class="input-wrap"><x-icon name="profile" /><input class="input {{ $errors->has('name') ? 'input-error' : '' }}" id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required placeholder="Enter your full name"></div>@error('name')<p class="error-message">{{ $message }}</p>@enderror</div>
    <div class="field"><label class="required" for="email">Email address</label><div class="input-wrap"><x-icon name="mail" /><input class="input {{ $errors->has('email') ? 'input-error' : '' }}" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="name@gmail.com"></div>@error('email')<p class="error-message">{{ $message }}</p>@enderror</div>
    <div class="field"><label class="required" for="password">Password</label><div class="input-wrap"><x-icon name="lock" /><input class="input {{ $errors->has('password') ? 'input-error' : '' }}" id="password" name="password" type="password" autocomplete="new-password" required data-password-strength placeholder="Create a strong password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><x-icon name="eye" /></button></div><div class="password-strength" data-password-strength-status aria-live="polite"><span></span><span></span><span></span><span></span><small>Use 8+ characters, upper/lowercase, a number, and a symbol.</small></div>@error('password')<p class="error-message">{{ $message }}</p>@enderror</div>
    <div class="field"><label class="required" for="password_confirmation">Confirm password</label><div class="input-wrap"><x-icon name="lock" /><input class="input {{ $errors->has('password_confirmation') ? 'input-error' : '' }}" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Confirm your password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show password"><x-icon name="eye" /></button></div>@error('password_confirmation')<p class="error-message">{{ $message }}</p>@enderror</div>
    <div class="field terms-field"><label class="checkbox-row"><input name="terms" type="checkbox" value="1" @checked(old('terms')) required><span>I agree to the <a class="link" href="#terms">Terms and Conditions</a>.</span></label>@error('terms')<p class="error-message">{{ $message }}</p>@enderror</div>
    <button class="btn btn-primary auth-submit" type="submit"><span>Submit access request</span><span class="button-spinner" aria-hidden="true"></span></button>
</form>

<p class="auth-footer">Already have an account? <a class="link" href="{{ route('login') }}" data-auth-switch>Sign in</a></p>
<p class="auth-disclaimer" id="terms">By submitting a request, you agree to our terms and privacy policy.</p>
@endsection
