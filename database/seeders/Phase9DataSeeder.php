<?php

namespace Database\Seeders;

use App\Models\UserSetting;
use App\Models\Notification;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Database\Seeder;

class Phase9DataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // UserSettings - 4 records (one for each setting type)
        $settingKeys = ['theme', 'language', 'notifications', 'privacy'];
        $settingValues = [
            ['dark', 'light'],
            ['en', 'es', 'fr', 'de'],
            ['on', 'off'],
            ['public', 'private', 'friends_only'],
        ];

        foreach ($settingKeys as $index => $key) {
            UserSetting::create([
                'user_id' => $user->id,
                'setting_key' => $key,
                'setting_value' => $settingValues[$index][array_rand($settingValues[$index])],
            ]);
        }

        // Notifications - 10 records
        $notificationTypes = ['email', 'sms', 'in_app'];
        $titles = [
            'New Message',
            'Update Available',
            'Activity Alert',
            'Security Notice',
            'Reminder',
            'Achievement Unlocked',
            'System Maintenance',
            'New Feature',
            'Payment Received',
            'Subscription Expiring',
        ];

        for ($i = 0; $i < 10; $i++) {
            Notification::create([
                'user_id' => $user->id,
                'type' => $notificationTypes[$i % 3],
                'title' => $titles[$i],
                'message' => 'Notification message for item ' . ($i + 1),
                'read_at' => $i < 5 ? now()->subDays(rand(1, 7)) : null,
                'action_url' => $i < 7 ? '/dashboard/' . rand(1, 100) : null,
            ]);
        }

        // AuditLogs - 10 records
        $actions = ['create', 'update', 'delete'];
        $modelNames = ['Contact', 'Recipe', 'Trip', 'Vehicle', 'Event', 'Message', 'Document', 'Flight', 'Hotel', 'Meal'];

        for ($i = 0; $i < 10; $i++) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => $actions[$i % 3],
                'model_name' => $modelNames[$i],
                'model_id' => rand(1, 100),
                'changes' => [
                    'field_' . ($i + 1) => ['old_value', 'new_value'],
                    'timestamp' => [now()->subHours(rand(1, 24))->toDateTimeString(), now()->toDateTimeString()],
                ],
                'ip_address' => '192.168.' . rand(0, 255) . '.' . rand(1, 255),
            ]);
        }

        // Backups - 6 records (mix of auto and manual)
        $backupTypes = ['auto', 'manual'];
        for ($i = 0; $i < 6; $i++) {
            Backup::create([
                'user_id' => $user->id,
                'backup_type' => $backupTypes[$i % 2],
                'backup_date' => now()->subDays(rand(1, 30))->toDateTimeString(),
                'file_path' => "backups/backup_" . now()->format('Y_m_d') . "_" . str_pad($i + 1, 3, '0', STR_PAD_LEFT) . ".zip",
                'file_size' => rand(1000000, 100000000), // 1MB to 100MB
            ]);
        }
    }
}
