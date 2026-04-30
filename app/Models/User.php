<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PhoneNumber;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
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
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isDirty(['first_name', 'last_name'])) {
                $user->name = $user->full_name;
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
            'settings' => $this->resolvedSettings(),
        ];
    }
}
