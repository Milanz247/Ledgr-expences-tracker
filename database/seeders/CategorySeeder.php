<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Expense Categories
            ['name' => 'Food & Dining', 'icon' => 'utensils', 'type' => 'expense'],
            ['name' => 'Groceries', 'icon' => 'shopping-cart', 'type' => 'expense'],
            ['name' => 'Rent', 'icon' => 'home', 'type' => 'expense'],
            ['name' => 'Transportation', 'icon' => 'car', 'type' => 'expense'],
            ['name' => 'Utilities', 'icon' => 'zap', 'type' => 'expense'],
            ['name' => 'Loan', 'icon' => 'credit-card', 'type' => 'expense'],
            ['name' => 'Entertainment', 'icon' => 'film', 'type' => 'expense'],
            ['name' => 'Healthcare', 'icon' => 'heart-pulse', 'type' => 'expense'],
            ['name' => 'Shopping', 'icon' => 'shopping-bag', 'type' => 'expense'],
            ['name' => 'Education', 'icon' => 'graduation-cap', 'type' => 'expense'],
            ['name' => 'Personal Care', 'icon' => 'scissors', 'type' => 'expense'],
            ['name' => 'Insurance', 'icon' => 'shield', 'type' => 'expense'],
            ['name' => 'Travel', 'icon' => 'plane', 'type' => 'expense'],
            ['name' => 'Fitness', 'icon' => 'dumbbell', 'type' => 'expense'],
            ['name' => 'Subscriptions', 'icon' => 'repeat', 'type' => 'expense'],
            ['name' => 'Bills', 'icon' => 'file-text', 'type' => 'expense'],
            ['name' => 'Other', 'icon' => 'more-horizontal', 'type' => 'expense'],

            // Income Categories
            ['name' => 'Salary', 'icon' => 'briefcase', 'type' => 'income'],
            ['name' => 'Freelance', 'icon' => 'laptop', 'type' => 'income'],
            ['name' => 'Investment', 'icon' => 'trending-up', 'type' => 'income'],
            ['name' => 'Gift', 'icon' => 'gift', 'type' => 'income'],
            ['name' => 'Bonus', 'icon' => 'award', 'type' => 'income'],
            ['name' => 'Refund', 'icon' => 'rotate-ccw', 'type' => 'income'],
            ['name' => 'Other Income', 'icon' => 'dollar-sign', 'type' => 'income'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'type' => $category['type'], 'user_id' => null],
                $category
            );
        }
    }
}
