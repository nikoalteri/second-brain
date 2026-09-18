<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Validates that a foreign key points to a row owned by the authenticated user.
 *
 * Plain `exists:table,id` bypasses Eloquent global scopes, so it accepts IDs
 * that belong to other users. Superadmins may reference any row.
 */
class OwnedByAuthenticatedUser implements ValidationRule
{
    public function __construct(private readonly string $table, private readonly bool $softDeletes = false) {}

    public static function accounts(): self
    {
        return new OwnedAccount;
    }

    public static function creditCards(): self
    {
        return new OwnedCreditCard;
    }

    public static function categories(): self
    {
        return new OwnedCategory;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query = DB::table($this->table)->where('id', $value);

        if ($this->softDeletes) {
            $query->whereNull('deleted_at');
        }

        if (! auth()->user()?->hasRole('superadmin')) {
            $query->where('user_id', auth()->id());
        }

        if (! $query->exists()) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
