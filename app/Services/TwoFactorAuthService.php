<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthService
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function otpAuthUrl(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if (! $this->engine->verifyKey($user->two_factor_secret, $code)) {
            return false;
        }

        // A TOTP code stays valid for about a minute and a half: remember the ones already
        // accepted so an observed code cannot be replayed. Cache::add is atomic, so two
        // simultaneous requests with the same code cannot both pass.
        return Cache::add('totp_used:' . $user->id . ':' . $code, true, now()->addMinutes(2));
    }

    /**
     * Consume a recovery code if valid, removing it so it can't be reused. Returns whether it
     * matched.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $normalized = strtoupper(trim($code));

        // The codes are re-read under a row lock: a copy of the user loaded before another
        // request consumed the same code must not be able to accept it a second time. The
        // column is an encrypted cast, so it is written through the model, not a raw update.
        $remaining = DB::transaction(function () use ($user, $normalized) {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            $codes = $locked?->two_factor_recovery_codes ?? [];
            $index = array_search($normalized, $codes, true);

            if ($locked === null || $index === false) {
                return null;
            }

            unset($codes[$index]);
            $codes = array_values($codes);
            $locked->forceFill(['two_factor_recovery_codes' => $codes])->save();

            return $codes;
        });

        if ($remaining === null) {
            return false;
        }

        // Keep the caller's instance in step with what was just stored.
        $user->setAttribute('two_factor_recovery_codes', $remaining);
        $user->syncOriginalAttribute('two_factor_recovery_codes');

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(4) . '-' . Str::random(4)))
            ->all();
    }
}
