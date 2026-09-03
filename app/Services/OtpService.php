<?php

namespace App\Services;

use App\Mail\OneTimePasswordMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OtpService
{
    private const LifetimeMinutes = 10;

    private const MaximumAttempts = 5;

    private const ResendCooldownSeconds = 60;

    public function generate(string $email): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::LifetimeMinutes);

        Cache::put($this->cacheKey($email), [
            'hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return $otp;
    }

    public function verify(string $email, string $otp): bool
    {
        $cacheKey = $this->cacheKey($email);
        $storedOtp = Cache::get($cacheKey);

        if (! is_array($storedOtp) || ! isset($storedOtp['hash'], $storedOtp['attempts'], $storedOtp['expires_at'])) {
            return false;
        }

        if ($storedOtp['attempts'] >= self::MaximumAttempts || now()->greaterThan($storedOtp['expires_at'])) {
            Cache::forget($cacheKey);

            return false;
        }

        if (Hash::check($otp, $storedOtp['hash'])) {
            Cache::forget($cacheKey);

            return true;
        }

        $storedOtp['attempts']++;

        if ($storedOtp['attempts'] >= self::MaximumAttempts) {
            Cache::forget($cacheKey);

            return false;
        }

        Cache::put($cacheKey, $storedOtp, now()->parse($storedOtp['expires_at']));

        return false;
    }

    public function sendOtp(string $email, string $otp): void
    {
        if (! $this->isDeliveryConfigured()) {
            throw new RuntimeException('A delivery-capable mailer is not configured.');
        }

        Mail::to($email)->send(new OneTimePasswordMail($otp));
        Cache::put($this->resendKey($email), true, now()->addSeconds(self::ResendCooldownSeconds));
    }

    public function isDeliveryConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    public function canResend(string $email): bool
    {
        return ! Cache::has($this->resendKey($email));
    }

    private function cacheKey(string $email): string
    {
        return 'otp:'.hash('sha256', mb_strtolower(trim($email)));
    }

    private function resendKey(string $email): string
    {
        return $this->cacheKey($email).':resend';
    }
}
