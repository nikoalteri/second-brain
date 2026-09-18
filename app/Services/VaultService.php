<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Gates access to sensitive card/IBAN data behind a short-lived, MFA-verified session that is
 * SEPARATE from the normal Sanctum access token. Encryption at rest (the models' 'encrypted'
 * casts) protects the data in the database; this protects it from anyone holding a normal,
 * already-authenticated session — an attacker with a stolen access token still can't read vault
 * fields without also passing a fresh TOTP/recovery-code check.
 */
class VaultService
{
    private const SESSION_TTL_MINUTES = 10;
    private const CACHE_PREFIX = 'vault_session:';

    public function __construct(private readonly TwoFactorAuthService $twoFactor) {}

    public function unlock(User $user, string $code, Request $request): string
    {
        if (! $user->hasTwoFactorEnabled()) {
            abort(403, 'Two-factor authentication must be enabled to use the vault.');
        }

        $verified = $this->twoFactor->verifyCode($user, $code) || $this->twoFactor->useRecoveryCode($user, $code);

        if (! $verified) {
            abort(422, 'Invalid code.');
        }

        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($user, $token), $this->epoch($user), now()->addMinutes(self::SESSION_TTL_MINUTES));

        $this->log($user, 'vault.unlock', $request);

        return $token;
    }

    public function isUnlocked(User $user, ?string $token): bool
    {
        if (! $token || ! $user->hasTwoFactorEnabled()) {
            return false;
        }

        return Cache::get($this->cacheKey($user, $token)) === $this->epoch($user);
    }

    /**
     * Locks every vault session of the user, whatever token created it. Called when a secret
     * that the unlock depends on changes (password, vault PIN, 2FA secret or activation), so an
     * unlock granted under the old secrets cannot outlive them.
     */
    public function revokeAll(User $user): void
    {
        Cache::forever($this->epochKey($user), (string) Str::uuid());
    }

    /**
     * Set/replace the 4-digit vault PIN that additionally gates CVV/PIN specifically (see
     * CreditCardSensitiveVaultController). Requires the account password, same as disabling 2FA
     * — changing this secret shouldn't be possible from a merely-stolen access token alone.
     */
    public function setPin(User $user, string $pin, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            abort(422, 'Incorrect password.');
        }

        $user->forceFill(['vault_pin' => $pin])->save();
        $this->clearPinLockout($user);
    }

    /**
     * A 4-digit PIN is only ~10,000 combinations, far weaker than the TOTP code that already
     * gates the vault as a whole — this lockout (5 attempts, then 15 minutes) is what actually
     * makes brute-forcing it impractical, since the throttle:5,1 route middleware alone would
     * still allow ~10,000 attempts in under an hour.
     */
    public function verifyPin(User $user, string $pin): bool
    {
        if (! $user->hasVaultPin()) {
            abort(422, 'No vault PIN has been set yet.');
        }

        if ($this->isPinLockedOut($user)) {
            abort(429, 'Too many incorrect attempts. Try again in a few minutes.');
        }

        if (Hash::check($pin, $user->vault_pin)) {
            $this->clearPinLockout($user);

            return true;
        }

        $this->registerPinFailure($user);

        return false;
    }

    // The failure counter goes through the RateLimiter: its hit() is a single atomic increment,
    // where a read followed by a write lets simultaneous wrong guesses share one count.
    private function isPinLockedOut(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->pinAttemptsKey($user), 5);
    }

    private function registerPinFailure(User $user): void
    {
        RateLimiter::hit($this->pinAttemptsKey($user), 15 * 60);
    }

    private function clearPinLockout(User $user): void
    {
        RateLimiter::clear($this->pinAttemptsKey($user));
    }

    private function pinAttemptsKey(User $user): string
    {
        return 'vault_pin_attempts:' . $user->id;
    }

    /**
     * The existing AuditLog model/table is shaped for CRUD diffs on regular models (action is a
     * DB-level ENUM of create/update/delete, model_name/model_id are NOT NULL) and doesn't fit
     * vault events (unlock has no target model; view/update actions aren't CRUD-diff shaped) —
     * repurposing it would either violate its constraints or blur its semantics for whatever
     * else reads it. Logging vault access to the standard log channel instead.
     */
    public function log(User $user, string $action, Request $request, ?string $modelName = null, ?int $modelId = null): void
    {
        Log::info('vault_access', [
            'action' => $action,
            'user_id' => $user->id,
            'model_name' => $modelName,
            'model_id' => $modelId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * The key is bound to the Sanctum access token that unlocked the vault: a different access
     * token of the same user (a new login, a refresh) starts locked, and logging out deletes the
     * access token so its unlock can never be presented again. Session-backed requests have no
     * token id and share one 'session' binding.
     */
    private function cacheKey(User $user, string $token): string
    {
        $accessTokenId = $user->currentAccessToken()?->id ?? 'session';

        return self::CACHE_PREFIX . $user->id . ':' . $accessTokenId . ':' . $token;
    }

    private function epoch(User $user): string
    {
        return (string) Cache::get($this->epochKey($user), 'initial');
    }

    private function epochKey(User $user): string
    {
        return 'vault_epoch:' . $user->id;
    }
}
