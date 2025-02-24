<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceiptItemsTableSeeder extends Seeder
{
    public function run()
    {
        // Disable foreign key checks to avoid constraint issues
        Schema::disableForeignKeyConstraints();

        // Truncate table (optional, use only if you want to clear existing data)
        DB::table('receipt_items')->truncate();

        // Get receipt IDs
        $receipts = DB::table('receipts')->get();

        if ($receipts->count() < 2) {
            $this->command->error('Not enough receipts found. Run ReceiptsTableSeeder first.');
            return;
        }

        // Insert sample receipt items
        DB::table('receipt_items')->insert([
            [
                'receipt_id' => $receipts[0]->id,
                'item_name'  => 'Milk',
                'quantity'   => 2,
                'price'      => 5.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'receipt_id' => $receipts[0]->id,
                'item_name'  => 'Bread',
                'quantity'   => 1,
                'price'      => 2.50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'receipt_id' => $receipts[1]->id,
                'item_name'  => 'Apples',
                'quantity'   => 4,
                'price'      => 1.20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'receipt_id' => $receipts[1]->id,
                'item_name'  => 'Eggs',
                'quantity'   => 1,
                'price'      => 3.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();

        $this->command->info('Receipt items seeded successfully');
    }
}
