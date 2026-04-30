<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 25)->nullable()->after('email');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('tax_code', 16)->nullable()->after('date_of_birth');
            $table->unique('tax_code');
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $name = trim((string) $user->name);

                    if ($name === '') {
                        continue;
                    }

                    $parts = preg_split('/\s+/', $name, 2) ?: [];
                    $firstName = $parts[0] ?? null;
                    $lastName = $parts[1] ?? null;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tax_code']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'date_of_birth',
                'tax_code',
            ]);
        });
    }
};
