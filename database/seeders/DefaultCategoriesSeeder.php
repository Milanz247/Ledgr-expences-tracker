<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class DefaultCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultCategories = [
            // Expense Categories
            ['name' => 'Food & Dining', 'icon' => 'Utensils', 'color' => '#f97316', 'type' => 'expense'],
            ['name' => 'Transportation', 'icon' => 'Car', 'color' => '#3b82f6', 'type' => 'expense'],
            ['name' => 'Shopping', 'icon' => 'ShoppingBag', 'color' => '#ec4899', 'type' => 'expense'],
            ['name' => 'Bills & Utilities', 'icon' => 'Zap', 'color' => '#eab308', 'type' => 'expense'],
            ['name' => 'Entertainment', 'icon' => 'Tv', 'color' => '#8b5cf6', 'type' => 'expense'],
            ['name' => 'Healthcare', 'icon' => 'Heart', 'color' => '#ef4444', 'type' => 'expense'],
            ['name' => 'Education', 'icon' => 'GraduationCap', 'color' => '#06b6d4', 'type' => 'expense'],
            ['name' => 'Rent', 'icon' => 'Home', 'color' => '#10b981', 'type' => 'expense'],

            // Income Categories
            ['name' => 'Salary', 'icon' => 'Briefcase', 'color' => '#22c55e', 'type' => 'income'],
            ['name' => 'Freelance', 'icon' => 'Laptop', 'color' => '#3b82f6', 'type' => 'income'],
            ['name' => 'Investment', 'icon' => 'TrendingUp', 'color' => '#10b981', 'type' => 'income'],
            ['name' => 'Gift', 'icon' => 'Gift', 'color' => '#ec4899', 'type' => 'income'],
        ];

        foreach ($defaultCategories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'user_id' => null],
                $category
            );
        }
    }
}
