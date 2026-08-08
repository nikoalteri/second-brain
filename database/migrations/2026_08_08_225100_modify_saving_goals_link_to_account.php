<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goals are now tied to a real account; progress is that account's live balance instead of
     * a separately-maintained current_amount. 'achieved' is no longer a stored status — it's
     * derived live from balance vs target — so any existing 'achieved' rows are normalized to
     * 'active' before the column is widened to a plain string (avoids the ENUM-migration
     * friction hit earlier with user_settings.setting_key).
     */
    public function up(): void
    {
        DB::table('saving_goals')->where('status', 'achieved')->update(['status' => 'active']);

        Schema::table('saving_goals', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('saving_goals', function (Blueprint $table) {
            $table->dropColumn('current_amount');
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteStatusColumn();

            return;
        }

        DB::statement("ALTER TABLE saving_goals MODIFY status VARCHAR(20) DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::table('saving_goals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->decimal('current_amount', 12, 2)->default(0)->after('target_amount');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE saving_goals MODIFY status ENUM('active', 'achieved', 'archived') DEFAULT 'active'");
    }

    private function rebuildSqliteStatusColumn(): void
    {
        Schema::table('saving_goals', function (Blueprint $table) {
            $table->string('status_tmp', 20)->default('active')->after('status');
        });

        DB::statement('UPDATE saving_goals SET status_tmp = status');

        Schema::table('saving_goals', function (Blueprint $table) {
            $table->dropIndex('saving_goals_status_index');
            $table->dropColumn('status');
        });

        Schema::table('saving_goals', function (Blueprint $table) {
            $table->renameColumn('status_tmp', 'status');
        });

        Schema::table('saving_goals', function (Blueprint $table) {
            $table->index('status');
        });
    }
};
