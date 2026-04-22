<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')
            ->select(['id', 'date'])
            ->whereNotNull('date')
            ->where(function ($query) {
                $query
                    ->whereNull('competence_month')
                    ->orWhere('competence_month', '');
            })
            ->orderBy('id')
            ->lazyById()
            ->each(function ($transaction) {
                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'competence_month' => Carbon::parse($transaction->date)->format('Y-m'),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('transactions')
            ->select(['id', 'date', 'competence_month'])
            ->whereNotNull('date')
            ->whereNotNull('competence_month')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($transaction) {
                if ($transaction->competence_month !== Carbon::parse($transaction->date)->format('Y-m')) {
                    return;
                }

                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'competence_month' => null,
                    ]);
            });
    }
};
