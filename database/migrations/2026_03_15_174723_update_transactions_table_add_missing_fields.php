<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->char('competence_month', 7)->nullable()->after('date');
            $table->boolean('is_transfer')->default(false)->after('notes');
            $table->uuid('transfer_pair_id')->nullable()->after('is_transfer');
            $table->enum('transfer_direction', ['in', 'out'])->nullable()->after('transfer_pair_id');
            $table->softDeletes();
            // Removed dropColumn('currency') because the column no longer exists
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['competence_month', 'is_transfer', 'transfer_pair_id', 'transfer_direction', 'deleted_at']);
            $table->string('currency')->default('EUR');
        });
    }
};
