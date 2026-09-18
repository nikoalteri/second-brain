<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'transactions' => ['transactions_subscription_renewal_idx', 'transactions_subscription_renewal_unique'],
        'credit_card_expenses' => ['credit_card_expenses_subscription_renewal_idx', 'credit_card_expenses_subscription_renewal_unique'],
    ];

    /**
     * A renewal (subscription + renewal date) was only looked up before being inserted, so two
     * overlapping runs could post it twice. The index becomes unique; rows without a subscription
     * are unaffected (NULLs never collide) and soft-deleted transactions keep counting, matching
     * the withTrashed() lookup the renewal code uses.
     */
    public function up(): void
    {
        // Existing duplicates would make the index fail half-way through a deploy: refuse first.
        foreach (array_keys(self::TABLES) as $table) {
            $duplicates = DB::table($table)
                ->whereNotNull('subscription_id')
                ->whereNotNull('subscription_renewal_date')
                ->groupBy('subscription_id', 'subscription_renewal_date')
                ->havingRaw('COUNT(*) > 1')
                ->select('subscription_id')
                ->get()
                ->count();

            if ($duplicates > 0) {
                throw new RuntimeException(
                    "{$duplicates} subscription renewal(s) are stored more than once in {$table}. "
                    .'Resolve them first (php artisan data:audit lists them), then run the migration again.'
                );
            }
        }

        foreach (self::TABLES as $table => [$plain, $unique]) {
            // The unique index is added first: the foreign key on subscription_id needs an index at all times.
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique(['subscription_id', 'subscription_renewal_date'], $unique));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($plain));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => [$plain, $unique]) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index(['subscription_id', 'subscription_renewal_date'], $plain));
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($unique));
        }
    }
};
