<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class ReceiptsTableSeeder extends Seeder
{
    public function run()
    {
        // Get the user ID for the user with email demo@example.com
        $user = User::where('email', 'demo@example.com')->first();

        if (!$user) {
            $this->command->error('User with email demo@example.com not found.');
            return;
        }

        // Disable foreign key checks to avoid constraint issues
        Schema::disableForeignKeyConstraints();

        // Truncate table (optional, use only if you want to clear existing data)
        DB::table('receipts')->truncate();

        // Insert sample receipts
        DB::table('receipts')->insert([
            [
                'user_id'     => $user->id,
                'store_name'  => 'SuperMart',
                'total_amount'=> 12.50,
                'date'        => now()->subDays(2),
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'user_id'     => $user->id,
                'store_name'  => 'Fresh Grocers',
                'total_amount'=> 7.80,
                'date'        => now()->subDays(1),
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();

        $this->command->info('Receipts seeded successfully');
    }
}
