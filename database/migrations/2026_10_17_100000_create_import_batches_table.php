<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Exactly one target: a bank account or a credit card.
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->string('status', 20)->default('uploaded');
            $table->string('file_format', 8);
            $table->string('original_filename', 190)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            // Declared on the real statement; compared against the computed balance by Phase 27.
            $table->decimal('statement_ending_balance', 15, 2)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('mapping')->nullable();
            $table->unsignedInteger('mapping_version')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
