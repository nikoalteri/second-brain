<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('airline');
            $table->string('flight_number');
            $table->date('departure_date');
            $table->time('departure_time');
            $table->date('arrival_date');
            $table->time('arrival_time');
            $table->string('departure_airport');
            $table->string('arrival_airport');
            $table->string('seat')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'trip_id']);
            $table->index(['user_id', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
