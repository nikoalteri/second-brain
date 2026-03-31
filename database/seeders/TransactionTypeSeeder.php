<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Earnings', 'is_income' => true],
            ['name' => 'Expenses', 'is_income' => false],
            ['name' => 'Transfer', 'is_income' => false],
            ['name' => 'Cashback', 'is_income' => true],
            ['name' => 'Income', 'is_income' => true],
            ['name' => 'Expense', 'is_income' => false],
            ['name' => 'Payment', 'is_income' => false],
        ];

        foreach ($types as $type) {
            TransactionType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
