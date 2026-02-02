<?php

namespace Database\Seeders;

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
            // Food & Meals
            ['name' => 'Breakfast', 'icon' => 'Coffee', 'color' => '#f59e0b', 'type' => 'expense'],
            ['name' => 'Lunch', 'icon' => 'Utensils', 'color' => '#f97316', 'type' => 'expense'],
            ['name' => 'Dinner', 'icon' => 'UtensilsCrossed', 'color' => '#ea580c', 'type' => 'expense'],
            ['name' => 'Snacks', 'icon' => 'Cookie', 'color' => '#d97706', 'type' => 'expense'],
            
            // Drinks & Lifestyle
            ['name' => 'Drinks', 'icon' => 'Wine', 'color' => '#dc2626', 'type' => 'expense'],
            ['name' => 'Cigarettes', 'icon' => 'Cigarette', 'color' => '#78716c', 'type' => 'expense'],
            
            // Housing & Bills
            ['name' => 'Rent', 'icon' => 'Home', 'color' => '#10b981', 'type' => 'expense'],
            ['name' => 'Electricity', 'icon' => 'Zap', 'color' => '#eab308', 'type' => 'expense'],
            ['name' => 'Water', 'icon' => 'Droplet', 'color' => '#0ea5e9', 'type' => 'expense'],
            ['name' => 'Internet', 'icon' => 'Wifi', 'color' => '#6366f1', 'type' => 'expense'],
            ['name' => 'Phone', 'icon' => 'Phone', 'color' => '#8b5cf6', 'type' => 'expense'],
            
            // Transportation
            ['name' => 'Fuel', 'icon' => 'Fuel', 'color' => '#ef4444', 'type' => 'expense'],
            ['name' => 'Transport', 'icon' => 'Bus', 'color' => '#3b82f6', 'type' => 'expense'],
            ['name' => 'Vehicle Maintenance', 'icon' => 'Wrench', 'color' => '#64748b', 'type' => 'expense'],
            
            // Shopping & Personal
            ['name' => 'Groceries', 'icon' => 'ShoppingCart', 'color' => '#22c55e', 'type' => 'expense'],
            ['name' => 'Shopping', 'icon' => 'ShoppingBag', 'color' => '#ec4899', 'type' => 'expense'],
            ['name' => 'Clothing', 'icon' => 'Shirt', 'color' => '#a855f7', 'type' => 'expense'],
            ['name' => 'Personal Care', 'icon' => 'Sparkles', 'color' => '#f472b6', 'type' => 'expense'],
            
            // Health & Wellness
            ['name' => 'Healthcare', 'icon' => 'Heart', 'color' => '#ef4444', 'type' => 'expense'],
            ['name' => 'Medicine', 'icon' => 'Pill', 'color' => '#f87171', 'type' => 'expense'],
            ['name' => 'Gym', 'icon' => 'Dumbbell', 'color' => '#14b8a6', 'type' => 'expense'],
            
            // Entertainment
            ['name' => 'Entertainment', 'icon' => 'Tv', 'color' => '#8b5cf6', 'type' => 'expense'],
            ['name' => 'Subscriptions', 'icon' => 'CreditCard', 'color' => '#0891b2', 'type' => 'expense'],
            ['name' => 'Movies', 'icon' => 'Film', 'color' => '#7c3aed', 'type' => 'expense'],
            
            // Education & Work
            ['name' => 'Education', 'icon' => 'GraduationCap', 'color' => '#06b6d4', 'type' => 'expense'],
            ['name' => 'Books', 'icon' => 'Book', 'color' => '#0d9488', 'type' => 'expense'],
            
            // Other Expenses
            ['name' => 'Gifts', 'icon' => 'Gift', 'color' => '#f43f5e', 'type' => 'expense'],
            ['name' => 'Donations', 'icon' => 'HeartHandshake', 'color' => '#e11d48', 'type' => 'expense'],
            ['name' => 'Other', 'icon' => 'MoreHorizontal', 'color' => '#94a3b8', 'type' => 'expense'],

            // Income Categories
            ['name' => 'Salary', 'icon' => 'Briefcase', 'color' => '#22c55e', 'type' => 'income'],
            ['name' => 'Freelance', 'icon' => 'Laptop', 'color' => '#3b82f6', 'type' => 'income'],
            ['name' => 'Business', 'icon' => 'Building2', 'color' => '#8b5cf6', 'type' => 'income'],
            ['name' => 'Investment', 'icon' => 'TrendingUp', 'color' => '#10b981', 'type' => 'income'],
            ['name' => 'Rental Income', 'icon' => 'Home', 'color' => '#14b8a6', 'type' => 'income'],
            ['name' => 'Gift Received', 'icon' => 'Gift', 'color' => '#ec4899', 'type' => 'income'],
            ['name' => 'Other Income', 'icon' => 'PlusCircle', 'color' => '#6366f1', 'type' => 'income'],
        ];

        foreach ($defaultCategories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'user_id' => null],
                $category
            );
        }
    }
}
