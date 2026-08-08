<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * setting_key was a fixed ENUM, so every new setting needed a schema migration. Widen it to
     * a plain string so display_currency (and future keys) only need an app-level constant —
     * validity is already enforced by UserSetting::normalizeValue().
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable('theme');

            return;
        }

        DB::statement("ALTER TABLE user_settings MODIFY setting_key VARCHAR(50) DEFAULT 'theme'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable('theme', enum: ['theme', 'language', 'notifications', 'privacy']);

            return;
        }

        DB::statement(
            "ALTER TABLE user_settings MODIFY setting_key ENUM('theme', 'language', 'notifications', 'privacy') DEFAULT 'theme'"
        );
    }

    private function rebuildSqliteTable(string $default, ?array $enum = null): void
    {
        Schema::create('user_settings_new', function (Blueprint $table) use ($default, $enum) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            if ($enum) {
                $table->enum('setting_key', $enum)->default($default);
            } else {
                $table->string('setting_key', 50)->default($default);
            }

            $table->string('setting_value')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'setting_key']);
            $table->index(['user_id']);
        });

        DB::statement('INSERT INTO user_settings_new SELECT * FROM user_settings');
        Schema::drop('user_settings');
        Schema::rename('user_settings_new', 'user_settings');
    }
};
