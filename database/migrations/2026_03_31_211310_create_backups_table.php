<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('backup_type', ['auto', 'manual'])->default('auto');
            $table->dateTime('backup_date');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'backup_type']);
            $table->index(['user_id', 'backup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
