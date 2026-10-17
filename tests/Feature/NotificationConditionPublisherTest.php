<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationSeverity;
use App\Models\Notification;
use App\Models\NotificationCondition;
use App\Models\User;
use App\Services\Notifications\ConditionSpec;
use App\Services\Notifications\NotificationConditionPublisher;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationConditionPublisherTest extends TestCase
{
    use RefreshDatabase;

    private const TRIGGERS = ['budget'];

    private NotificationConditionPublisher $publisher;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-17 02:05:00'));
        $this->publisher = new NotificationConditionPublisher;
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function spec(NotificationSeverity $severity = NotificationSeverity::WARNING, int $subjectId = 1, string $url = '/budgets'): ConditionSpec
    {
        return new ConditionSpec('budget', 'category', $subjectId, '2026-10', $severity, 'Budget alert', 'Food is at 85%', $url);
    }

    private function notificationCount(?User $user = null): int
    {
        return Notification::query()->withoutUserScope()->where('user_id', ($user ?? $this->user)->id)->count();
    }

    private function condition(?User $user = null, int $subjectId = 1): NotificationCondition
    {
        return NotificationCondition::query()
            ->where('user_id', ($user ?? $this->user)->id)
            ->where('subject_id', $subjectId)
            ->firstOrFail();
    }

    public function test_new_condition_creates_one_notification_and_reruns_create_none(): void
    {
        $this->publisher->reconcile($this->user->id, self::TRIGGERS, [$this->spec()]);
        $this->publisher->reconcile($this->user->id, self::TRIGGERS, [$this->spec()]);

        Carbon::setTestNow(Carbon::parse('2026-10-18 02:05:00'));
        $this->publisher->reconcile($this->user->id, self::TRIGGERS, [$this->spec()]);

        $this->assertSame(1, $this->notificationCount());
        $notification = Notification::query()->withoutUserScope()->firstOrFail();
        $this->assertSame('category', $notification->related_model);
        $this->assertSame(1, $notification->related_id);
        $this->assertSame('warning', $notification->severity);
        $this->assertSame($this->condition()->id, $notification->notification_condition_id);
    }

    public function test_read_notification_does_not_reopen_condition(): void
    {
        $this->publisher->publish($this->user->id, $this->spec());
        Notification::query()->withoutUserScope()->update(['read_at' => now()]);

        $this->publisher->publish($this->user->id, $this->spec());

        $this->assertSame(1, $this->notificationCount());
        $this->assertTrue($this->condition()->active);
    }

    public function test_deleted_notification_is_not_recreated_while_condition_stays_active(): void
    {
        $this->publisher->publish($this->user->id, $this->spec());
        Notification::query()->withoutUserScope()->first()->forceDelete();

        $this->publisher->publish($this->user->id, $this->spec());
        $this->assertSame(0, $this->notificationCount());

        $this->publisher->reconcile($this->user->id, self::TRIGGERS, [$this->spec(NotificationSeverity::CRITICAL)]);
        $this->assertSame(1, $this->notificationCount());
    }

    public function test_soft_deleted_notification_is_not_recreated(): void
    {
        $this->publisher->publish($this->user->id, $this->spec());
        Notification::query()->withoutUserScope()->first()->delete();

        $this->publisher->publish($this->user->id, $this->spec());

        $this->assertSame(0, Notification::query()->withoutUserScope()->count());
        $this->assertSame(1, Notification::query()->withoutUserScope()->withTrashed()->count());
    }

    public function test_escalation_creates_one_more_notification_and_falling_severity_creates_none(): void
    {
        $this->publisher->publish($this->user->id, $this->spec(NotificationSeverity::WARNING));
        $this->publisher->publish($this->user->id, $this->spec(NotificationSeverity::CRITICAL));
        $this->publisher->publish($this->user->id, $this->spec(NotificationSeverity::CRITICAL));

        $this->assertSame(2, $this->notificationCount());

        $this->publisher->publish($this->user->id, $this->spec(NotificationSeverity::EXCEEDED));
        $this->publisher->publish($this->user->id, $this->spec(NotificationSeverity::CRITICAL));

        $this->assertSame(2, $this->notificationCount());
        $condition = $this->condition();
        $this->assertSame(NotificationSeverity::CRITICAL, $condition->current_severity);
        $this->assertSame(NotificationSeverity::CRITICAL, $condition->max_notified_severity);
        $this->assertSame(1, $condition->episode);
    }

    public function test_current_severity_follows_a_fall(): void
    {
        $this->publisher->publish($this->user->id, $this->spec(NotificationSeverity::CRITICAL));
        $this->publisher->publish($this->user->id, $this->spec(NotificationSeverity::WARNING));

        $this->assertSame(NotificationSeverity::WARNING, $this->condition()->current_severity);
        $this->assertSame(1, $this->notificationCount());
    }

    public function test_missing_condition_is_resolved_and_reactivation_starts_new_episode(): void
    {
        $this->publisher->reconcile($this->user->id, self::TRIGGERS, [$this->spec()]);

        $result = $this->publisher->reconcile($this->user->id, self::TRIGGERS, []);
        $this->assertSame(['created' => 0, 'resolved' => 1], $result);
        $condition = $this->condition();
        $this->assertFalse($condition->active);
        $this->assertNotNull($condition->resolved_at);

        $result = $this->publisher->reconcile($this->user->id, self::TRIGGERS, [$this->spec()]);
        $this->assertSame(['created' => 1, 'resolved' => 0], $result);
        $condition = $this->condition();
        $this->assertTrue($condition->active);
        $this->assertSame(2, $condition->episode);
        $this->assertNull($condition->resolved_at);
        $this->assertSame(2, $this->notificationCount());
        $this->assertSame([1, 2], Notification::query()->withoutUserScope()->orderBy('episode')->pluck('episode')->all());
    }

    public function test_reconcile_does_not_resolve_conditions_of_other_triggers(): void
    {
        $other = new ConditionSpec('loan', 'loan_payment', 5, '2026-10-20', NotificationSeverity::WARNING, 'Loan', 'Due soon', '/loans');
        $this->publisher->publish($this->user->id, $other);

        $this->publisher->reconcile($this->user->id, self::TRIGGERS, []);

        $this->assertTrue($this->condition(subjectId: 5)->active);
    }

    public function test_owners_are_isolated(): void
    {
        $other = User::factory()->create();

        $this->publisher->reconcile($this->user->id, self::TRIGGERS, [$this->spec()]);
        $this->publisher->reconcile($other->id, self::TRIGGERS, [$this->spec()]);
        $this->publisher->reconcile($other->id, self::TRIGGERS, []);

        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(1, $this->notificationCount($other));
        $this->assertTrue($this->condition()->active);
        $this->assertFalse($this->condition($other)->active);
    }

    public function test_open_ended_constant_period_key_is_supported(): void
    {
        $spec = new ConditionSpec('credit_card_limit', 'credit_card', 3, 'open', NotificationSeverity::WARNING, 'Card', 'Near limit', '/credit-cards');

        $this->publisher->publish($this->user->id, $spec);
        $this->publisher->publish($this->user->id, $spec);

        $this->assertSame(1, $this->notificationCount());
    }

    public function test_action_url_must_be_application_relative(): void
    {
        foreach (['https://evil.test', '//evil.test', 'javascript:alert(1)', '/ok\\evil', 'relative'] as $url) {
            try {
                $this->spec(url: $url);
                $this->fail("Accepted {$url}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('/budgets?month=2026-10', $this->spec(url: '/budgets?month=2026-10')->actionUrl);
    }

    public function test_unique_keys_reject_duplicate_identities(): void
    {
        $this->publisher->publish($this->user->id, $this->spec());
        $condition = $this->condition();

        try {
            NotificationCondition::create([
                'user_id' => $this->user->id, 'trigger' => 'budget', 'subject_type' => 'category',
                'subject_id' => 1, 'period_key' => '2026-10',
            ]);
            $this->fail('Duplicate condition accepted');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        Notification::query()->withoutUserScope()->create([
            'user_id' => $this->user->id, 'title' => 'x', 'message' => 'y', 'type' => 'in_app',
            'notification_condition_id' => $condition->id, 'episode' => 1, 'severity' => 'warning',
        ]);
    }

    public function test_notifications_without_condition_remain_valid(): void
    {
        $id = DB::table('notifications')->insertGetId([
            'user_id' => $this->user->id, 'type' => 'in_app', 'title' => 'Legacy', 'message' => 'Old row',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'user_id' => $this->user->id, 'type' => 'in_app', 'title' => 'Legacy 2', 'message' => 'Old row',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $legacy = Notification::query()->withoutUserScope()->findOrFail($id);

        $this->assertNull($legacy->notification_condition_id);
        $this->assertNull($legacy->related_model);
        $this->assertSame(2, $this->notificationCount());
    }
}
