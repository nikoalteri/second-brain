<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\AccountTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, AuditLog> */
    private function trail(string $model, ?int $id = null)
    {
        return AuditLog::query()->withoutGlobalScopes()
            ->where('model_name', $model)
            ->when($id, fn ($q) => $q->where('model_id', $id))
            ->orderBy('id')
            ->get();
    }

    public function test_api_writes_are_recorded_with_owner_actor_and_ip(): void
    {
        Sanctum::actingAs($this->user);

        $id = $this->postJson('/api/v1/accounts', [
            'name' => 'Main', 'type' => 'checking', 'balance' => 100, 'currency' => 'EUR',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/accounts/{$id}", ['name' => 'Renamed'])->assertOk();
        $this->deleteJson("/api/v1/accounts/{$id}")->assertNoContent();

        $rows = $this->trail('Account', $id);

        $this->assertSame(['create', 'update', 'delete'], $rows->pluck('action')->all());
        $this->assertSame([$this->user->id], $rows->pluck('user_id')->unique()->values()->all());
        $this->assertSame([$this->user->id], $rows->pluck('actor_id')->unique()->values()->all());
        $this->assertNotNull($rows->first()->ip_address);
        $this->assertSame('Main', $rows[0]->changes['name']);
        $this->assertSame(['old' => 'Main', 'new' => 'Renamed'], $rows[1]->changes['name']);
    }

    public function test_a_superadmin_change_is_attributed_to_the_admin_and_belongs_to_the_owner(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('superadmin');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $account = Account::factory()->create(['user_id' => $this->user->id, 'name' => 'Before']);

        $this->actingAs($admin);
        $account->update(['name' => 'After']);

        $row = $this->trail('Account', $account->id)->last();

        $this->assertSame('update', $row->action);
        $this->assertSame($this->user->id, $row->user_id);
        $this->assertSame($admin->id, $row->actor_id);
    }

    public function test_system_changes_without_a_session_have_no_actor(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id, 'name' => 'Before']);
        $account->update(['name' => 'After']);

        $row = $this->trail('Account', $account->id)->last();

        $this->assertNull($row->actor_id);
        $this->assertNull($row->ip_address);
        $this->assertSame($this->user->id, $row->user_id);
    }

    public function test_sensitive_columns_are_recorded_as_redacted_never_as_values(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id, 'iban' => 'IT60X0542811101000000123456']);
        $account->update(['iban' => 'IT00Y0000000000000000000000']);

        $card = CreditCard::factory()->create([
            'user_id' => $this->user->id, 'card_number' => '4111111111111111', 'cvv' => '123', 'pin' => '4321',
        ]);
        $card->update(['cvv' => '999']);

        $dump = json_encode(AuditLog::query()->withoutGlobalScopes()->get()->pluck('changes')->all());

        foreach (['IT60X0542811101000000123456', 'IT00Y0000000000000000000000', '4111111111111111', '"123"', '4321', '999'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }

        $this->assertSame('[redacted]', $this->trail('Account', $account->id)->first()->changes['iban']);
        $this->assertSame('[redacted]', $this->trail('CreditCard', $card->id)->last()->changes['cvv']);
    }

    public function test_system_maintained_columns_do_not_flood_the_trail(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 0, 'opening_balance' => 0]);
        $before = $this->trail('Account', $account->id)->count();

        $type = TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
        Transaction::create([
            'user_id' => $this->user->id, 'account_id' => $account->id, 'transaction_type_id' => $type->id,
            'amount' => -10, 'date' => '2026-05-01', 'description' => 'Coffee',
        ]);

        $this->assertNotSame(0.0, (float) $account->fresh()->balance);
        $this->assertSame($before, $this->trail('Account', $account->id)->count());
        $this->assertCount(1, $this->trail('Transaction'));
        $this->assertSame('create', $this->trail('Transaction')->first()->action);
    }

    public function test_a_save_that_changes_nothing_writes_no_row(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $before = $this->trail('Account', $account->id)->count();

        $account->update(['name' => $account->name]);
        $account->touch();

        $this->assertSame($before, $this->trail('Account', $account->id)->count());
    }

    public function test_deleting_a_hard_deleted_record_keeps_a_snapshot(): void
    {
        $loan = Loan::factory()->create(['user_id' => $this->user->id, 'name' => 'Car loan']);
        $loan->delete();

        $row = $this->trail('Loan', $loan->id)->last();

        $this->assertSame('delete', $row->action);
        $this->assertSame('Car loan', $row->changes['name']);
    }

    public function test_transfers_are_traced_leg_by_leg(): void
    {
        $from = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 100]);
        $to = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 0]);

        app(AccountTransferService::class)->transfer($from, $to, 40, '2026-05-01');

        $this->assertCount(2, $this->trail('Transaction'));
    }

    public function test_the_trail_cannot_be_edited_or_deleted(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $row = $this->trail('Account', $account->id)->first();

        $this->expectException(\LogicException::class);
        $row->delete();
    }

    public function test_a_user_only_sees_their_own_trail(): void
    {
        $other = User::factory()->create();
        Account::factory()->create(['user_id' => $this->user->id]);
        Account::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user);

        $this->assertSame([$this->user->id], AuditLog::query()->pluck('user_id')->unique()->values()->all());
    }
}
