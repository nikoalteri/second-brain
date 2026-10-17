<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_conditions', function (Blueprint $table) {
            // Bumped on every publish so a stale evaluation can detect it was overtaken.
            $table->unsignedBigInteger('publish_seq')->default(0)->after('episode');
        });
    }

    public function down(): void
    {
        Schema::table('notification_conditions', function (Blueprint $table) {
            $table->dropColumn('publish_seq');
        });
    }
};
