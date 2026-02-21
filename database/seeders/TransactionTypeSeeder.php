<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Earnings',
                'color' => '#10B981',
                'icon' => 'heroicon-o-arrow-up',
                'is_income' => true,
            ],
            [
                'name' => 'Expenses',
                'color' => '#EF4444',
                'icon' => 'heroicon-o-arrow-down',
                'is_income' => false,
            ],
            [
                'name' => 'Transfer',
                'color' => '#3B82F6',
                'icon' => 'heroicon-o-arrows-up-down',
            ],
            [
                'name' => 'Cashback',
                'color' => '#F59E0B',
                'icon' => 'heroicon-o-arrow-up-circle',
                'is_income' => true,
            ],
        ];

        foreach ($types as $type) {
            TransactionType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
