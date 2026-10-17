<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            // Encrypted JSON: date, amount_minor, description, category. Matching runs in PHP,
            // so nothing sensitive needs to be queryable in clear.
            $table->longText('normalized')->nullable()->after('raw_cells');
            $table->string('error', 190)->nullable()->after('normalized');
            // Keyed hash of owner + target + date + amount + description + occurrence: not reversible.
            $table->string('fingerprint', 64)->nullable()->after('error');
            $table->string('duplicate_class', 10)->nullable()->after('fingerprint');
            $table->json('flags')->nullable()->after('duplicate_class');

            $table->index('fingerprint');
        });

        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('mapping');
            $table->timestamps();

            $table->unique(['user_id', 'account_id']);
            $table->unique(['user_id', 'credit_card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mappings');

        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropIndex(['fingerprint']);
            $table->dropColumn(['normalized', 'error', 'fingerprint', 'duplicate_class', 'flags']);
        });
    }
};
