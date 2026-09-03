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
    <button class="auth-theme-toggle" type="button" data-theme-toggle aria-label="Toggle color theme"><x-icon name="moon" /></button>
    <section class="auth-panel"><div class="auth-card" data-auth-card>
        <div class="auth-brand"><span class="brand-mark"><x-icon name="medicine" /></span><span><strong>{{ $systemSettings['system_name'] ?? 'MediStock RHU' }}</strong><small>{{ $systemSettings['facility_name'] ?? 'Rural Health Unit' }}</small></span></div>
        @if(session('success'))<div class="alert alert-success" role="status"><x-icon name="check" />{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-error" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
        @yield('content')
    </div></section>
    <section class="auth-visual"><div class="auth-visual-content"><span class="auth-kicker">Secure RHU inventory</span><h2>Essential medicines.<br>Always accounted for.</h2><p>Track every batch, protect patients from expired stock, and keep your health unit ready for the community.</p><div class="auth-features"><div class="auth-feature"><x-icon name="boxes" /><strong>Batch-level control</strong><span>FEFO stock movement</span></div><div class="auth-feature"><x-icon name="alert" /><strong>Early warnings</strong><span>Stock and expiry alerts</span></div><div class="auth-feature"><x-icon name="audit" /><strong>Accountable</strong><span>Complete audit history</span></div></div></div></section>
</main>
</body>
</html>
