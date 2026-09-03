<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class PlaintextUserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $submittedPassword = $credentials['password'] ?? null;
        $storedPassword = $user->getAuthPassword();

        if (! is_string($submittedPassword) || ! is_string($storedPassword)) {
            return false;
        }

        if (password_get_info($storedPassword)['algo'] !== null) {
            return Hash::check($submittedPassword, $storedPassword);
        }

        return hash_equals($storedPassword, $submittedPassword);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void {}
}
