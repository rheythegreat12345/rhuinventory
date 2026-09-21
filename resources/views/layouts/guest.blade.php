<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ $systemSettings['system_name'] ?? 'MediStock RHU' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
<main class="auth-page">
    <div class="auth-orb auth-orb-one"></div><div class="auth-orb auth-orb-two"></div>
    <div class="auth-particles" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <section class="auth-panel"><div class="auth-card" data-auth-card>
        <div class="auth-brand"><span class="brand-mark"><img class="brand-logo" src="{{ asset('images/sudipen-rhu-seal.jpg') }}" alt="Sudipen Rural Health Unit seal"></span><span><strong>{{ $systemSettings['system_name'] ?? 'MediStock RHU' }}</strong><small>{{ $systemSettings['facility_name'] ?? 'Rural Health Unit' }}</small></span></div>
        @if(session('success'))<div class="alert alert-success" role="status"><x-icon name="check" />{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-error" role="alert"><x-icon name="alert" />{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-error" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
        @yield('content')
    </div></section>
    <section class="auth-visual" data-auth-visual-rotator aria-live="polite">
        <div class="auth-photo-bubbles" aria-hidden="true">
            <div class="auth-photo-bubble auth-photo-bubble-one"><img src="{{ asset('images/rhu-team-community.jpg') }}" alt=""></div>
            <div class="auth-photo-bubble auth-photo-bubble-two"><img src="{{ asset('images/rhu-team-celebration.jpg') }}" alt=""></div>
            <div class="auth-photo-bubble auth-photo-bubble-three"><img src="{{ asset('images/rhu-team-service.jpg') }}" alt=""></div>
        </div>
        <div class="auth-visual-content">
            <span class="auth-kicker" data-auth-visual-kicker>Secure RHU inventory</span>
            <h2 data-auth-visual-title>Essential medicines.<br>Always accounted for.</h2>
            <p data-auth-visual-description>Proverbs 17:22 — “A cheerful heart is good medicine, but a crushed spirit dries up the bones.”</p>
        </div>
    </section>
</main>
</body>
</html>
