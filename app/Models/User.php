<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PhoneNumber;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'tax_code',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'filament_recovery_codes',
        'vault_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth'     => 'date',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'filament_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'vault_pin' => 'hashed',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /*
     * Filament (admin panel) app authentication. The TOTP secret is the same one the API and the
     * SPA use, so a user has a single authenticator entry; the panel's own recovery codes live in
     * `filament_recovery_codes` because Filament stores them hashed.
     */

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->hasTwoFactorEnabled() ? $this->two_factor_secret : null;
    }

    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void
    {
        if ($secret === null) {
            $this->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'filament_recovery_codes' => null,
            ])->save();

            return;
        }

        $this->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->filament_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[\SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['filament_recovery_codes' => $codes])->save();
    }

    public function hasVaultPin(): bool
    {
        return $this->vault_pin !== null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isDirty(['first_name', 'last_name'])) {
                $user->name = $user->full_name;
            }
        });

        // A deactivated user must not keep working sessions.
        static::updated(function (self $user): void {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        // The vault unlock rests on the password, the vault PIN and the 2FA secret/activation:
        // when any of them changes, existing unlocks must stop working. Recovery codes are left
        // out on purpose: consuming one is how a vault gets unlocked in the first place.
        static::updated(function (self $user): void {
            if ($user->wasChanged(['password', 'vault_pin', 'two_factor_secret', 'two_factor_confirmed_at'])) {
                app(\App\Services\VaultService::class)->revokeAll($user);
            }
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $firstName = trim((string) ($this->attributes['first_name'] ?? ''));
                $lastName = trim((string) ($this->attributes['last_name'] ?? ''));
                $fullName = trim($firstName.' '.$lastName);

                return $fullName !== '' ? $fullName : (string) ($this->attributes['name'] ?? '');
            },
        );
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? trim($value) : null,
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? trim($value) : null,
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? trim($value) : null,
        );
    }

    protected function taxCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? strtoupper(str_replace(' ', '', trim($value))) : null,
        );
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function transactionCategories(): HasMany
    {
        return $this->hasMany(TransactionCategory::class);
    }

    public function userSettings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && (
                $this->hasRole('superadmin')
                || $this->getAllPermissions()->contains('name', 'module.adminpanel')
            );
    }

    public function resolvedSettings(): array
    {
        $settings = $this->relationLoaded('userSettings')
            ? $this->userSettings
            : $this->userSettings()->get();

        $resolved = UserSetting::DEFAULTS;

        foreach ($settings as $setting) {
            if (! in_array($setting->setting_key, UserSetting::activeKeys(), true)) {
                continue;
            }

            $resolved[$setting->setting_key] = UserSetting::normalizeValue(
                $setting->setting_key,
                $setting->setting_value,
            );
        }

        return $resolved;
    }

    public function toFrontendPayload(): array
    {
        $roles = $this->getRoleNames()->values()->all();
        $phone = PhoneNumber::split($this->phone);

        return [
            'id'         => $this->id,
            'name'       => $this->full_name,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'phone_country_code' => $phone['country_code'],
            'phone_number' => $phone['local_number'],
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'tax_code'   => $this->tax_code,
            'roles'      => $roles,
            'is_admin' => in_array('superadmin', $roles, true),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'settings' => $this->resolvedSettings(),
        ];
    }
}
