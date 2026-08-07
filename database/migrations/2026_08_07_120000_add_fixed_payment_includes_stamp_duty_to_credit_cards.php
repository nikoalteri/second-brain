<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->boolean('fixed_payment_includes_stamp_duty')
                ->default(false)
                ->after('stamp_duty_amount')
                ->comment('true = fixed_payment already contains the stamp duty (principal = installment - interest - stamp duty); false = stamp duty is charged on top of fixed_payment');
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropColumn('fixed_payment_includes_stamp_duty');
        });
    }
};
