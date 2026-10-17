<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('related_model', 60)->nullable()->after('action_url');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_model');
            $table->foreignId('notification_condition_id')
                ->nullable()
                ->after('related_id')
                ->constrained('notification_conditions')
                ->nullOnDelete();
            $table->unsignedInteger('episode')->nullable()->after('notification_condition_id');
            $table->string('severity', 16)->nullable()->after('episode');

            $table->unique(
                ['notification_condition_id', 'episode', 'severity'],
                'notifications_condition_episode_severity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_condition_episode_severity_unique');
            $table->dropConstrainedForeignId('notification_condition_id');
            $table->dropColumn(['related_model', 'related_id', 'episode', 'severity']);
        });
    }
};
