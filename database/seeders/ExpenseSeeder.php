<?php

namespace Database\Seeders;

use App\Models\Expense;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    /**
     * Default expense categories for shop_id = 1.
     */
    protected array $defaultCategories = [
        'Rent',
        'Electricity',
        'Water',
        'Internet',
        'Salaries',
        'Transport',
        'Maintenance',
        'Marketing',
        'Office Supplies',
        'Repairs',
        'Fuel',
        'Insurance',
        'Legal & Professional Fees',
        'Miscellaneous',
        'Inventory Loss',
        'Equipment Purchase',
        'Travel Expense',
        'Entertainment',
        'Tax & Government Fees',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shopId = 1;

        foreach ($this->defaultCategories as $title) {
            Expense::firstOrCreate(
                [
                    'shop_id' => $shopId,
                    'expense_title' => $title,
                ],
                [
                    'expense_title' => $title,
                    'shop_id' => $shopId,
                ]
            );
        }
    }
}
