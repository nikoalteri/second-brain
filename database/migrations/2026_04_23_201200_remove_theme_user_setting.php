<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_settings')
            ->where('setting_key', 'theme')
            ->delete();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE user_settings MODIFY setting_key ENUM('language', 'notifications', 'privacy') NOT NULL DEFAULT 'language'"
            );
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE user_settings MODIFY setting_key ENUM('theme', 'language', 'notifications', 'privacy') NOT NULL DEFAULT 'theme'"
            );
        }
    }
};
