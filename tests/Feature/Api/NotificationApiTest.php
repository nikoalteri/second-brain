<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, array $attrs = []): Notification
    {
        return Notification::query()->withoutUserScope()->create($attrs + [
            'user_id' => $user->id, 'type' => 'in_app', 'title' => 'Budget alert', 'message' => 'Food at 85%',
            'action_url' => '/home', 'severity' => 'warning',
        ]);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/1/read')->assertUnauthorized();
    }

    public function test_list_returns_only_own_notifications_newest_first_with_unread_count_and_safe_fields(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Carbon::setTestNow('2026-10-01');
        $old = $this->notify($user, ['title' => 'Old']);
        Carbon::setTestNow('2026-10-02');
        $new = $this->notify($user, ['title' => 'New']);
        $read = $this->notify($user, ['title' => 'Read', 'read_at' => now()]);
        $this->notify($other, ['title' => 'Foreign']);
        Carbon::setTestNow();

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/notifications')->assertOk();

        $this->assertSame([$read->id, $new->id, $old->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertSame(2, $response->json('unread_count'));
        $this->assertSame(
            ['action_url', 'created_at', 'id', 'message', 'read_at', 'severity', 'title', 'type'],
            collect(array_keys($response->json('data.0')))->sort()->values()->all()
        );
    }

    public function test_superadmin_only_sees_own_notifications(): void
    {
        Role::findOrCreate('superadmin');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $other = User::factory()->create();
        $mine = $this->notify($admin);
        $foreign = $this->notify($other);

        Sanctum::actingAs($admin);

        $this->assertSame([$mine->id], collect($this->getJson('/api/v1/notifications')->json('data'))->pluck('id')->all());
        $this->patchJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_mark_read_is_idempotent_and_foreign_ids_404(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->notify($user);
        $foreign = $this->notify($other);
        Sanctum::actingAs($user);

        Carbon::setTestNow('2026-10-17 10:00:00');
        $this->patchJson("/api/v1/notifications/{$own->id}/read")->assertOk()->assertJsonPath('data.id', $own->id);
        $firstRead = $own->fresh()->read_at;

        Carbon::setTestNow('2026-10-17 11:00:00');
        $this->patchJson("/api/v1/notifications/{$own->id}/read")->assertOk();
        Carbon::setTestNow();

        $this->assertEquals($firstRead, $own->fresh()->read_at);
        $this->patchJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
        $this->patchJson('/api/v1/notifications/999999/read')->assertNotFound();
    }

    public function test_mark_read_ignores_client_supplied_fields(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->notify($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/notifications/{$own->id}/read", [
            'user_id' => $other->id, 'title' => 'Hacked', 'action_url' => 'https://evil.test',
        ])->assertOk();

        $own->refresh();
        $this->assertSame($user->id, $own->user_id);
        $this->assertSame('Budget alert', $own->title);
        $this->assertSame('/home', $own->action_url);
    }

    public function test_page_size_is_bounded_and_paginates(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 105) as $i) {
            $this->notify($user, ['title' => "N{$i}"]);
        }
        Sanctum::actingAs($user);

        $this->assertCount(100, $this->getJson('/api/v1/notifications?per_page=5000')->json('data'));
        $this->assertCount(20, $this->getJson('/api/v1/notifications')->json('data'));

        $page = $this->getJson('/api/v1/notifications?per_page=100');
        $this->assertNotNull($page->json('meta.next_cursor'));
        $this->assertCount(5, $this->getJson('/api/v1/notifications?per_page=100&cursor='.$page->json('meta.next_cursor'))->json('data'));
    }

    public function test_unsafe_action_urls_are_not_exposed_and_soft_deleted_are_hidden(): void
    {
        $user = User::factory()->create();
        $this->notify($user, ['title' => 'Bad', 'action_url' => 'https://evil.test/x']);
        $this->notify($user, ['title' => 'Gone'])->delete();
        Sanctum::actingAs($user);

        $data = $this->getJson('/api/v1/notifications')->json('data');

        $this->assertCount(1, $data);
        $this->assertNull($data[0]['action_url']);
    }
}
