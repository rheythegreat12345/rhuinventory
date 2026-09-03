<?php

use App\Mail\OneTimePasswordMail;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

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

test('an inactive user cannot sign in', function () {
    $user = userWithPermissions([], 'inactive');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login validation does not reveal whether an account exists', function () {
    $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'The email, password, or account status is invalid.']);

    expect(User::query()->count())->toBe(0);
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
        ->assertSee('Continue with Google')
        ->assertSee('data-password-toggle', false);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Terms and Conditions')
        ->assertSee('data-password-strength', false);

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Send reset link');
});
