<?php

namespace Tests\Feature\Settings;

use App\Models\UserSetting;
use App\Models\Notification;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // UserSetting Tests
    #[Test]
    public function user_can_create_setting()
    {
        $data = [
            'user_id' => $this->user->id,
            'setting_key' => 'theme',
            'setting_value' => 'dark',
        ];

        $setting = UserSetting::create($data);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $this->user->id,
            'setting_key' => 'theme',
            'setting_value' => 'dark',
        ]);
    }

    #[Test]
    public function user_setting_has_user_scoping()
    {
        UserSetting::create([
            'user_id' => $this->user->id,
            'setting_key' => 'language',
            'setting_value' => 'en',
        ]);

        $otherUser = User::factory()->create();
        UserSetting::create([
            'user_id' => $otherUser->id,
            'setting_key' => 'language',
            'setting_value' => 'es',
        ]);

        $this->assertEquals(1, $this->user->userSettings()->count());
    }

    #[Test]
    public function user_setting_can_be_soft_deleted()
    {
        $setting = UserSetting::create([
            'user_id' => $this->user->id,
            'setting_key' => 'notifications',
            'setting_value' => 'off',
        ]);

        $setting->delete();

        $this->assertSoftDeleted($setting);
        $this->assertEquals(0, UserSetting::count());
        $this->assertEquals(1, UserSetting::withTrashed()->count());
    }

    #[Test]
    public function user_setting_validates_keys()
    {
        $validKeys = ['theme', 'language', 'notifications', 'privacy'];

        foreach ($validKeys as $key) {
            $setting = UserSetting::create([
                'user_id' => $this->user->id,
                'setting_key' => $key,
                'setting_value' => 'test_value',
            ]);
            $this->assertEquals($key, $setting->setting_key);
        }
    }

    // Notification Tests
    #[Test]
    public function user_can_create_notification()
    {
        $data = [
            'user_id' => $this->user->id,
            'type' => 'email',
            'title' => 'Welcome',
            'message' => 'Welcome to our app',
            'action_url' => '/dashboard',
        ];

        $notification = Notification::create($data);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'email',
            'title' => 'Welcome',
        ]);
    }

    #[Test]
    public function notification_has_read_tracking()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'in_app',
            'title' => 'Message',
            'message' => 'You have a new message',
            'read_at' => null,
        ]);

        $this->assertNull($notification->read_at);

        $notification->update(['read_at' => now()]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[Test]
    public function notification_validates_types()
    {
        $validTypes = ['email', 'sms', 'in_app'];

        foreach ($validTypes as $type) {
            $notification = Notification::create([
                'user_id' => $this->user->id,
                'type' => $type,
                'title' => "Notification {$type}",
                'message' => 'Test notification',
            ]);
            $this->assertEquals($type, $notification->type);
        }
    }

    #[Test]
    public function notification_can_have_action_url()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'in_app',
            'title' => 'Order Ready',
            'message' => 'Your order is ready for pickup',
            'action_url' => '/orders/123',
        ]);

        $this->assertEquals('/orders/123', $notification->action_url);
    }

    #[Test]
    public function notification_can_be_soft_deleted()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'email',
            'title' => 'Test',
            'message' => 'Test message',
        ]);

        $notification->delete();

        $this->assertSoftDeleted($notification);
    }

    // AuditLog Tests
    #[Test]
    public function user_can_create_audit_log()
    {
        $data = [
            'user_id' => $this->user->id,
            'action' => 'create',
            'model_name' => 'Contact',
            'model_id' => 1,
            'changes' => ['name' => 'John Doe'],
            'ip_address' => '127.0.0.1',
        ];

        $log = AuditLog::create($data);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'create',
            'model_name' => 'Contact',
        ]);
        $this->assertEquals(['name' => 'John Doe'], $log->changes);
    }

    #[Test]
    public function audit_log_validates_actions()
    {
        $validActions = ['create', 'update', 'delete'];

        foreach ($validActions as $action) {
            $log = AuditLog::create([
                'user_id' => $this->user->id,
                'action' => $action,
                'model_name' => 'TestModel',
                'model_id' => 1,
            ]);
            $this->assertEquals($action, $log->action);
        }
    }

    #[Test]
    public function audit_log_tracks_changes()
    {
        $log = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'update',
            'model_name' => 'User',
            'model_id' => $this->user->id,
            'changes' => [
                'email' => ['old@example.com', 'new@example.com'],
                'name' => ['Old Name', 'New Name'],
            ],
        ]);

        $this->assertIsArray($log->changes);
        $this->assertArrayHasKey('email', $log->changes);
        $this->assertArrayHasKey('name', $log->changes);
    }

    #[Test]
    public function audit_log_can_store_ip_address()
    {
        $log = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'create',
            'model_name' => 'Article',
            'model_id' => 1,
            'ip_address' => '192.168.1.100',
        ]);

        $this->assertEquals('192.168.1.100', $log->ip_address);
    }

    #[Test]
    public function audit_log_can_be_soft_deleted()
    {
        $log = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'delete',
            'model_name' => 'Post',
            'model_id' => 5,
        ]);

        $log->delete();

        $this->assertSoftDeleted($log);
    }

    // Backup Tests
    #[Test]
    public function user_can_create_backup()
    {
        $data = [
            'user_id' => $this->user->id,
            'backup_type' => 'manual',
            'backup_date' => now()->toDateTimeString(),
            'file_path' => 'backups/backup_2024_03_31.zip',
            'file_size' => 1024000,
        ];

        $backup = Backup::create($data);

        $this->assertDatabaseHas('backups', [
            'user_id' => $this->user->id,
            'backup_type' => 'manual',
            'file_path' => 'backups/backup_2024_03_31.zip',
        ]);
    }

    #[Test]
    public function backup_validates_types()
    {
        $validTypes = ['auto', 'manual'];

        foreach ($validTypes as $type) {
            $backup = Backup::create([
                'user_id' => $this->user->id,
                'backup_type' => $type,
                'backup_date' => now()->toDateTimeString(),
                'file_path' => "backups/backup_{$type}.zip",
            ]);
            $this->assertEquals($type, $backup->backup_type);
        }
    }

    #[Test]
    public function backup_tracks_file_metadata()
    {
        $backup = Backup::create([
            'user_id' => $this->user->id,
            'backup_type' => 'auto',
            'backup_date' => now()->toDateTimeString(),
            'file_path' => 'backups/auto_backup.zip',
            'file_size' => 5242880, // 5MB
        ]);

        $this->assertEquals(5242880, $backup->file_size);
    }

    #[Test]
    public function backup_can_be_soft_deleted()
    {
        $backup = Backup::create([
            'user_id' => $this->user->id,
            'backup_type' => 'manual',
            'backup_date' => now()->toDateTimeString(),
            'file_path' => 'backups/test_backup.zip',
        ]);

        $backup->delete();

        $this->assertSoftDeleted($backup);
        $this->assertEquals(0, Backup::count());
        $this->assertEquals(1, Backup::withTrashed()->count());
    }

    #[Test]
    public function backup_has_user_scoping()
    {
        Backup::create([
            'user_id' => $this->user->id,
            'backup_type' => 'auto',
            'backup_date' => now()->toDateTimeString(),
            'file_path' => 'backups/user1_backup.zip',
        ]);

        $otherUser = User::factory()->create();
        Backup::create([
            'user_id' => $otherUser->id,
            'backup_type' => 'auto',
            'backup_date' => now()->toDateTimeString(),
            'file_path' => 'backups/user2_backup.zip',
        ]);

        $this->assertEquals(1, $this->user->backups()->count());
    }
}
