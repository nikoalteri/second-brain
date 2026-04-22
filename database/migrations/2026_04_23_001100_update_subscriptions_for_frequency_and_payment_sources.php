<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_frequencies')->insert([
            [
                'name' => 'Monthly',
                'slug' => 'monthly',
                'months_interval' => 1,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Annual',
                'slug' => 'annual',
                'months_interval' => 12,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Every 2 Years',
                'slug' => 'biennial',
                'months_interval' => 24,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('subscription_frequency_id')
                ->nullable()
                ->after('annual_cost')
                ->constrained('subscription_frequencies')
                ->nullOnDelete();
            $table->foreignId('credit_card_id')
                ->nullable()
                ->after('account_id')
                ->constrained()
                ->nullOnDelete();
        });

        $frequencyIds = DB::table('subscription_frequencies')
            ->pluck('id', 'slug');

        DB::table('subscriptions')
            ->select(['id', 'frequency'])
            ->orderBy('id')
            ->get()
            ->each(function (object $subscription) use ($frequencyIds): void {
                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'subscription_frequency_id' => $frequencyIds[$subscription->frequency] ?? $frequencyIds['monthly'],
                    ]);
            });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('frequency')->default('monthly')->after('annual_cost');
        });

        $frequencySlugs = DB::table('subscription_frequencies')
            ->pluck('slug', 'id');

        DB::table('subscriptions')
            ->select(['id', 'subscription_frequency_id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $subscription) use ($frequencySlugs): void {
                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'frequency' => $frequencySlugs[$subscription->subscription_frequency_id] ?? 'monthly',
                    ]);
            });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_frequency_id');
            $table->dropConstrainedForeignId('credit_card_id');
        });

        DB::table('subscription_frequencies')->truncate();
    }
};
