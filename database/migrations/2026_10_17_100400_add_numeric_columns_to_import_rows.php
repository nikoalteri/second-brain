<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            // Column positions that were real numbers in an XLSX file (empty for CSV).
            $table->json('numeric_columns')->nullable()->after('raw_cells');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropColumn('numeric_columns');
        });
    }
};
