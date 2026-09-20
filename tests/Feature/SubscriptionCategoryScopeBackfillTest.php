<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionCategoryScopeBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        // RefreshDatabase already ran this migration once against an empty database as part of
        // the schema setup, before this test's rows existed — re-running it here applies the
        // backfill against the legacy-style data this test just inserted, the same way it would
        // against real rows that predate the scope column.
        (require base_path('database/migrations/2026_09_20_134313_backfill_subscription_only_category_scope.php'))->up();
    }

    #[Test]
    public function a_category_used_only_by_a_subscription_is_backfilled_to_subscription_scope(): void
    {
        $user = User::factory()->create();
        $category = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Streaming',
            'scope' => 'generic',
            'is_active' => true,
        ]);
        Subscription::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);

        $this->runMigration();

        $this->assertSame('subscription', $category->fresh()->scope);
    }

    #[Test]
    public function the_parent_of_a_backfilled_category_is_backfilled_too(): void
    {
        $user = User::factory()->create();
        $parent = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Subscriptions',
            'scope' => 'generic',
            'is_active' => true,
        ]);
        $child = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'name' => 'Streaming',
            'scope' => 'generic',
            'is_active' => true,
        ]);
        Subscription::factory()->create(['user_id' => $user->id, 'category_id' => $child->id]);

        $this->runMigration();

        $this->assertSame('subscription', $child->fresh()->scope);
        $this->assertSame('subscription', $parent->fresh()->scope, 'parent must be backfilled too, or the picker drops the child under it');
    }

    #[Test]
    public function a_category_also_used_by_a_regular_transaction_is_left_generic(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);

        $category = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Shared',
            'scope' => 'generic',
            'is_active' => true,
        ]);
        Subscription::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id,
        ]);

        $this->runMigration();

        $this->assertSame('generic', $category->fresh()->scope, 'a category shared with real transactions must not be hidden from the generic picker');
    }

    #[Test]
    public function a_category_never_used_by_a_subscription_is_untouched(): void
    {
        $user = User::factory()->create();
        $category = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Groceries',
            'scope' => 'generic',
            'is_active' => true,
        ]);

        $this->runMigration();

        $this->assertSame('generic', $category->fresh()->scope);
    }
}
