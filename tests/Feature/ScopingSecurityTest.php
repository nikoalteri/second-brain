<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\CategoryBudget;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopingSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed one row owned by userA and one owned by userB for every HasUserScoping model.
     *
     * @return array<class-string, array{a: int, b: int}>
     */
    private function seedPair(User $userA, User $userB): array
    {
        $accountA = Account::factory()->create(['user_id' => $userA->id]);
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        $transactionA = Transaction::factory()->create(['user_id' => $userA->id, 'account_id' => $accountA->id]);
        $transactionB = Transaction::factory()->create(['user_id' => $userB->id, 'account_id' => $accountB->id]);

        $categoryBudgetA = CategoryBudget::factory()->create(['user_id' => $userA->id]);
        $categoryBudgetB = CategoryBudget::factory()->create(['user_id' => $userB->id]);

        $creditCardA = CreditCard::factory()->create(['user_id' => $userA->id]);
        $creditCardB = CreditCard::factory()->create(['user_id' => $userB->id]);

        $loanA = Loan::factory()->create(['user_id' => $userA->id]);
        $loanB = Loan::factory()->create(['user_id' => $userB->id]);

        $subscriptionA = Subscription::factory()->create(['user_id' => $userA->id]);
        $subscriptionB = Subscription::factory()->create(['user_id' => $userB->id]);

        $auditLogA = AuditLog::withoutGlobalScopes()->create([
            'user_id' => $userA->id, 'action' => 'create', 'model_name' => 'Account', 'model_id' => 1,
        ]);
        $auditLogB = AuditLog::withoutGlobalScopes()->create([
            'user_id' => $userB->id, 'action' => 'create', 'model_name' => 'Account', 'model_id' => 2,
        ]);

        $backupA = Backup::withoutGlobalScopes()->create([
            'user_id' => $userA->id, 'backup_type' => 'manual', 'backup_date' => now(),
        ]);
        $backupB = Backup::withoutGlobalScopes()->create([
            'user_id' => $userB->id, 'backup_type' => 'manual', 'backup_date' => now(),
        ]);

        $notificationA = Notification::withoutGlobalScopes()->create([
            'user_id' => $userA->id, 'type' => 'in_app', 'title' => 'A title', 'message' => 'A message',
        ]);
        $notificationB = Notification::withoutGlobalScopes()->create([
            'user_id' => $userB->id, 'type' => 'in_app', 'title' => 'B title', 'message' => 'B message',
        ]);

        $transactionCategoryA = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $userA->id, 'name' => 'CatA', 'is_active' => true,
        ]);
        $transactionCategoryB = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $userB->id, 'name' => 'CatB', 'is_active' => true,
        ]);

        $userSettingA = UserSetting::withoutGlobalScopes()->create([
            'user_id' => $userA->id, 'setting_key' => 'theme', 'setting_value' => 'dark',
        ]);
        $userSettingB = UserSetting::withoutGlobalScopes()->create([
            'user_id' => $userB->id, 'setting_key' => 'theme', 'setting_value' => 'light',
        ]);

        return [
            Account::class => ['a' => $accountA->id, 'b' => $accountB->id],
            AuditLog::class => ['a' => $auditLogA->id, 'b' => $auditLogB->id],
            Backup::class => ['a' => $backupA->id, 'b' => $backupB->id],
            CategoryBudget::class => ['a' => $categoryBudgetA->id, 'b' => $categoryBudgetB->id],
            CreditCard::class => ['a' => $creditCardA->id, 'b' => $creditCardB->id],
            Loan::class => ['a' => $loanA->id, 'b' => $loanB->id],
            Notification::class => ['a' => $notificationA->id, 'b' => $notificationB->id],
            Subscription::class => ['a' => $subscriptionA->id, 'b' => $subscriptionB->id],
            Transaction::class => ['a' => $transactionA->id, 'b' => $transactionB->id],
            TransactionCategory::class => ['a' => $transactionCategoryA->id, 'b' => $transactionCategoryB->id],
            UserSetting::class => ['a' => $userSettingA->id, 'b' => $userSettingB->id],
        ];
    }

    public function test_every_user_scoped_model_hides_other_users_rows_from_authenticated_reads(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $ids = $this->seedPair($userA, $userB);

        Sanctum::actingAs($userA);

        foreach ($ids as $modelClass => $pair) {
            $visible = $modelClass::query()->pluck('id')->all();
            $this->assertContains($pair['a'], $visible, "$modelClass hid userA's own row");
            $this->assertNotContains($pair['b'], $visible, "$modelClass LEAKED userB's row to userA");
        }
    }

    public function test_superadmin_sees_every_users_rows_for_user_scoped_models(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $ids = $this->seedPair($userA, $userB);

        Role::create(['name' => 'superadmin']);
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        Sanctum::actingAs($admin);

        foreach ($ids as $modelClass => $pair) {
            $visible = $modelClass::query()->pluck('id')->all();
            $this->assertContains($pair['a'], $visible, "$modelClass hid userA's row from superadmin");
            $this->assertContains($pair['b'], $visible, "$modelClass hid userB's row from superadmin");
        }
    }

    public function test_unauthenticated_console_context_sees_all_users_rows(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $ids = $this->seedPair($userA, $userB);

        // No Sanctum::actingAs() call: this documents the intentional no-op that
        // scheduled/console commands depend on (D-04). If a future change makes
        // the scope fail-closed for unauthenticated context, this test must fail
        // loudly rather than being silently adjusted.
        foreach ($ids as $modelClass => $pair) {
            $visible = $modelClass::query()->pluck('id')->all();
            $this->assertContains($pair['a'], $visible, "$modelClass hid userA's row in unauthenticated context");
            $this->assertContains($pair['b'], $visible, "$modelClass hid userB's row in unauthenticated context");
        }
    }
}
