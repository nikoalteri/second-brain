<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every transaction_categories row defaulted to scope: generic when the column was added,
     * so any category already used by an existing subscription needs to move to scope:
     * subscription — otherwise the subscription form's category picker (which now filters by
     * scope) shows nothing for existing users. Also backfills the parent of a matched category,
     * since the picker groups children under their parent and drops children whose parent isn't
     * in the scope-filtered result.
     *
     * Categories also used by a regular transaction are left alone: flipping a shared category
     * to scope: subscription would hide it from the transaction form's picker instead, trading
     * one gap for another. Those need a human decision (split it, or leave it generic), not an
     * automatic guess.
     */
    public function up(): void
    {
        $usedByTransactions = DB::table('transactions')
            ->whereNotNull('transaction_category_id')
            ->pluck('transaction_category_id')
            ->unique();

        $categoryIds = DB::table('subscriptions')
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->unique()
            ->diff($usedByTransactions);

        if ($categoryIds->isEmpty()) {
            return;
        }

        $parentIds = DB::table('transaction_categories')
            ->whereIn('id', $categoryIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->diff($usedByTransactions);

        $allIds = $categoryIds->merge($parentIds)->unique();

        DB::table('transaction_categories')->whereIn('id', $allIds)->update(['scope' => 'subscription']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally not reversible: there's no record of which rows this migration changed
        // versus categories a user has since scoped to subscription themselves.
    }
};
