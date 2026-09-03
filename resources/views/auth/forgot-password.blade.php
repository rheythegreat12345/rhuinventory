@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
<div class="auth-heading"><span class="auth-eyebrow">Password recovery</span><h1>Reset your password</h1><p>Enter your account email and we’ll send a secure password reset link.</p></div>
<form class="auth-form" data-auth-form method="POST" action="{{ route('password.email') }}">@csrf
    <div class="field"><label class="required" for="email">Email address</label><div class="input-wrap"><x-icon name="mail" /><input class="input {{ $errors->has('email') ? 'input-error' : '' }}" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required placeholder="you@rhu.gov.ph"></div>@error('email')<p class="error-message">{{ $message }}</p>@enderror</div>
    <button class="btn btn-primary auth-submit" type="submit"><span>Send reset link</span><span class="button-spinner" aria-hidden="true"></span></button>
    <a class="btn btn-secondary auth-social" href="{{ route('login') }}" data-auth-switch>Back to sign in</a>
</form>
@endsection
