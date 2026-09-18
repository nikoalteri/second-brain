<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferPairIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $from;

    private Account $to;

    private int $outId;

    private int $inId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->from = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 100]);
        $this->to = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 0]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/transfers', [
            'from_account_id' => $this->from->id,
            'to_account_id' => $this->to->id,
            'amount' => 20,
            'date' => '2026-01-10',
        ])->assertCreated();

        $this->outId = $response->json('out.id');
        $this->inId = $response->json('in.id');
    }

    private function balances(): array
    {
        return [(float) $this->from->fresh()->balance, (float) $this->to->fresh()->balance];
    }

    public function test_editing_the_out_leg_amount_updates_the_in_leg_and_keeps_the_total(): void
    {
        $this->putJson("/api/v1/transactions/{$this->outId}", ['amount' => 30])->assertOk();

        $this->assertSame([70.0, 30.0], $this->balances());
        $this->assertSame(30.0, (float) Transaction::find($this->inId)->amount);
        $this->assertSame(-30.0, (float) Transaction::find($this->outId)->amount);
    }

    public function test_editing_the_in_leg_amount_updates_the_out_leg_and_keeps_the_total(): void
    {
        $this->putJson("/api/v1/transactions/{$this->inId}", ['amount' => 30])->assertOk();

        $this->assertSame([70.0, 30.0], $this->balances());
        $this->assertSame(-30.0, (float) Transaction::find($this->outId)->amount);
    }

    public function test_editing_the_date_of_one_leg_updates_the_other(): void
    {
        $this->putJson("/api/v1/transactions/{$this->inId}", ['date' => '2026-02-15'])->assertOk();

        $out = Transaction::find($this->outId);
        $this->assertSame('2026-02-15', $out->date->toDateString());
        $this->assertSame('2026-02', $out->competence_month);
    }

    public function test_moving_the_in_leg_to_another_account_updates_the_out_leg_target(): void
    {
        $third = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 0]);

        $this->putJson("/api/v1/transactions/{$this->inId}", ['account_id' => $third->id])->assertOk();

        $this->assertSame($third->id, Transaction::find($this->outId)->to_account_id);
        $this->assertSame([80.0, 0.0], $this->balances());
        $this->assertSame(20.0, (float) $third->fresh()->balance);
    }

    public function test_deleting_the_in_leg_deletes_the_out_leg_and_restores_balances(): void
    {
        $this->deleteJson("/api/v1/transactions/{$this->inId}")->assertNoContent();

        $this->assertNull(Transaction::find($this->outId));
        $this->assertNull(Transaction::find($this->inId));
        $this->assertSame([100.0, 0.0], $this->balances());
    }

    public function test_deleting_the_out_leg_deletes_the_in_leg_and_restores_balances(): void
    {
        $this->deleteJson("/api/v1/transactions/{$this->outId}")->assertNoContent();

        $this->assertNull(Transaction::find($this->inId));
        $this->assertSame([100.0, 0.0], $this->balances());
    }

    public function test_restoring_one_leg_restores_the_pair_and_the_balances(): void
    {
        $this->deleteJson("/api/v1/transactions/{$this->outId}")->assertNoContent();

        Transaction::withTrashed()->find($this->outId)->restore();

        $this->assertNotNull(Transaction::find($this->outId));
        $this->assertNotNull(Transaction::find($this->inId));
        $this->assertSame([80.0, 20.0], $this->balances());
    }

    public function test_restoring_the_in_leg_restores_the_pair_and_the_balances(): void
    {
        $this->deleteJson("/api/v1/transactions/{$this->inId}")->assertNoContent();

        Transaction::withTrashed()->find($this->inId)->restore();

        $this->assertNotNull(Transaction::find($this->outId));
        $this->assertSame([80.0, 20.0], $this->balances());
    }
}
