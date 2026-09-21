<?php

use App\Mail\OneTimePasswordMail;
use App\Models\AuditLog;
use App\Models\InventoryNotification;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

test('an active user can sign in and sign out', function () {
    $user = userWithPermissions(['medicines.view']);

    expect($user->getRawOriginal('password'))->toBe('password');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'login')->where('user_id', $user->id)->exists())->toBeTrue();

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('an inactive user with the correct password is told to wait for approval', function () {
    $user = userWithPermissions([], 'inactive');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHas('error', 'Your account is waiting for administrator approval. You will be able to sign in after it is approved.')
        ->assertSessionHasNoErrors();

    $this->assertGuest();
});

test('an inactive user with the wrong password receives the generic login error', function () {
    $user = userWithPermissions([], 'inactive');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'The email, password, or account status is invalid.']);

    $this->assertGuest();
});

test('login validation does not reveal whether an account exists', function () {
    $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'The email, password, or account status is invalid.']);

    expect(User::query()->count())->toBe(0);
});

test('login normalizes the email address before authentication', function () {
    $user = userWithPermissions();

    $this->post(route('login.store'), [
        'email' => Str::upper($user->email),
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('the demo account credentials can authenticate', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(DemoAccountSeeder::class);

    $this->post(route('login.store'), [
        'email' => 'demo@rhu.test',
        'password' => 'demo1234',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::query()->where('email', 'demo@rhu.test')->firstOrFail());
});

test('a password change validates and stores plaintext credentials', function () {
    $user = userWithPermissions();

    $this->actingAs($user)->put(route('profile.password'), [
        'current_password' => 'password',
        'password' => 'updated-password',
        'password_confirmation' => 'updated-password',
    ])->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Password changed successfully.');

    expect(Hash::check('updated-password', $user->fresh()->getRawOriginal('password')))->toBeTrue();
});

test('a password reset stores hashed credentials', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Reset!123Password',
        'password_confirmation' => 'Reset!123Password',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Reset!123Password', $user->fresh()->getRawOriginal('password')))->toBeTrue();
});

test('a new account requires accepted terms and sends an email verification code', function () {
    config(['mail.default' => 'smtp']);
    Mail::fake();

    $this->post(route('register.store'), [
        'name' => 'New Health Worker',
        'email' => 'health.worker@example.test',
        'password' => 'Secure!Pass123',
        'password_confirmation' => 'Secure!Pass123',
        'terms' => '1',
    ])->assertRedirect(route('register.otp'))
        ->assertSessionHas('registration_data.email', 'health.worker@example.test');

    Mail::assertSent(OneTimePasswordMail::class, fn (OneTimePasswordMail $mail): bool => $mail->hasTo('health.worker@example.test'));
});

test('a verified registration requires administrator approval before sign in', function () {
    $role = Role::factory()->create(['slug' => 'rhu-staff', 'is_active' => true]);
    $administratorRole = Role::factory()->create(['slug' => 'administrator', 'is_active' => true]);
    $administrator = User::factory()->create(['role_id' => $administratorRole->id]);
    $email = 'pending.staff@example.test';
    $otp = app(OtpService::class)->generate($email);

    $this->withSession([
        'registration_data' => [
            'name' => 'Pending Staff',
            'email' => $email,
            'password' => Hash::make('Secure!Pass123'),
            'phone' => null,
            'job_title' => null,
        ],
    ])->post(route('register.verify-otp'), ['otp' => $otp])
        ->assertRedirect(route('login'))
        ->assertSessionHas('success', 'Your account request was submitted. An administrator must approve it before you can sign in.');

    $this->assertGuest();
    $this->assertDatabaseHas('users', [
        'email' => $email,
        'role_id' => $role->id,
        'status' => 'inactive',
    ]);

    $pendingUser = User::query()->where('email', $email)->firstOrFail();

    expect(InventoryNotification::query()
        ->where('user_id', $administrator->id)
        ->where('type', 'account_approval_requested')
        ->where('data->user_id', $pendingUser->id)
        ->exists())->toBeTrue();
});

test('a verified Google registration requires administrator approval before sign in', function () {
    $role = Role::factory()->create(['slug' => 'rhu-staff', 'is_active' => true]);
    $email = 'pending.google@example.test';
    $otp = app(OtpService::class)->generate($email);

    $this->withSession([
        'google_user_data' => [
            'name' => 'Pending Google Staff',
            'email' => $email,
            'google_id' => 'google-user-id',
            'avatar' => null,
        ],
    ])->post(route('auth.google.verify-otp'), ['otp' => $otp])
        ->assertRedirect(route('login'))
        ->assertSessionHas('success', 'Your account request was submitted. An administrator must approve it before you can sign in.');

    $this->assertGuest();
    $this->assertDatabaseHas('users', [
        'email' => $email,
        'role_id' => $role->id,
        'status' => 'inactive',
    ]);
});

test('an administrator can approve a pending account', function () {
    $administratorRole = Role::factory()->create(['slug' => 'administrator', 'is_active' => true]);
    $administrator = User::factory()->create(['role_id' => $administratorRole->id]);
    $pendingUser = User::factory()->create(['status' => 'inactive']);

    $this->actingAs($administrator)->put(route('users.approve', $pendingUser))
        ->assertRedirect(route('users.show', $pendingUser))
        ->assertSessionHas('success', 'User account approved. The staff member can now sign in.');

    expect($pendingUser->fresh()->status)->toBe('active');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user_approved',
        'auditable_id' => $pendingUser->id,
    ]);
});

test('registration does not claim an otp was sent when mail delivery is not configured', function () {
    config(['mail.default' => 'log']);

    $this->post(route('register.store'), [
        'name' => 'New Health Worker',
        'email' => 'health.worker@example.test',
        'password' => 'Secure!Pass123',
        'password_confirmation' => 'Secure!Pass123',
        'terms' => '1',
    ])->assertSessionHasErrors([
        'email' => 'The verification email could not be delivered. Please check the mail configuration and try again.',
    ])->assertSessionMissing('registration_data');
});

test('a new account must accept the terms', function () {
    $this->post(route('register.store'), [
        'name' => 'New Health Worker',
        'email' => 'health.worker@example.test',
        'password' => 'Secure!Pass123',
        'password_confirmation' => 'Secure!Pass123',
    ])->assertSessionHasErrors('terms');
});

test('forgot password has a generic success response for unknown addresses', function () {
    config(['mail.default' => 'smtp']);

    $this->post(route('password.email'), ['email' => 'missing@example.test'])
        ->assertSessionHas('success', 'If an account exists for this email, password reset instructions have been sent.');
});

test('guest authentication pages render the modern form controls', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Continue with Google')
        ->assertSee('data-password-toggle', false)
        ->assertSee('data-auth-visual-rotator', false)
        ->assertDontSee('Every batch, traceable')
        ->assertSee('Proverbs 17:22')
        ->assertSee('/images/sudipen-rhu-seal.jpg')
        ->assertSee('/images/rhu-team-community.jpg')
        ->assertSee('/images/rhu-team-celebration.jpg')
        ->assertSee('/images/rhu-team-service.jpg');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Terms and Conditions')
        ->assertDontSee('Continue with Google')
        ->assertSee('data-password-strength', false);

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Send reset link');
});

test('the default facility identity uses Sudipen', function () {
    $this->seed(SettingSeeder::class);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sudipen Rural Health Unit');
});

test('google sign-in reports when OAuth credentials are missing', function () {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
        'services.google.redirect' => null,
    ]);

    $this->get(route('auth.google'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Google sign-in is not configured. Please contact the system administrator.')
        ->assertSessionHasNoErrors();
});

test('an unauthorized Google account cannot access the inventory system', function () {
    config([
        'services.google.allowed_emails' => ['authorized@rhu.gov.ph'],
        'services.google.allowed_domains' => ['rhu.gov.ph'],
    ]);

    $googleUser = Mockery::mock();
    $googleUser->shouldReceive('getEmail')->once()->andReturn('unapproved@gmail.com');

    $googleDriver = Mockery::mock();
    $googleDriver->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($googleDriver);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'This Google account is not authorized to access the RHU inventory system.');
});

test('guest pages display flashed error messages once at the page level', function () {
    $this->withSession([
        'error' => 'Google sign-in is not configured. Please contact the system administrator.',
    ])->get(route('login'))
        ->assertOk()
        ->assertSee('Google sign-in is not configured. Please contact the system administrator.')
        ->assertDontSee('id="email-error"', false);
});
