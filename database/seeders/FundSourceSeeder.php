<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FundSource;
use App\Models\User;

class FundSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'milanmadusankamms@gmail.com')->first();

        if ($user) {
            FundSource::firstOrCreate(
                ['user_id' => $user->id, 'source_name' => 'Cash & Wallet'],
                [
                    'user_id' => $user->id,
                    'source_name' => 'Cash & Wallet',
                    'amount' => 0.00,
                    'description' => 'Default cash and wallet fund source',
                ]
            );
        }
    }
}
