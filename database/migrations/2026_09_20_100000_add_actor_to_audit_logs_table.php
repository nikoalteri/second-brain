<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // user_id stays the owner of the audited record (it drives scoping); actor_id is who
            // made the change. Null means the system did (scheduler, console).
            $table->foreignId('actor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['model_name', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropIndex(['model_name', 'model_id']);
            $table->dropColumn('actor_id');
        });
    }
};
