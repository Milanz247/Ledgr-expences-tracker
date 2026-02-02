<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'milanmadusankamms@gmail.com'],
            [
                'name' => 'Milan Madusanka',
                'email' => 'milanmadusankamms@gmail.com',
                'password' => Hash::make('janmilan'),
                'email_verified_at' => now(),
            ]
        );
    }
}
