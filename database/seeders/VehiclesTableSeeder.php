<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehiclesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Fetch the user ID of demo@example.com
        $user = DB::table('users')->where('email', 'demo@example.com')->first();

        if ($user) {
            DB::table('vehicles')->insert([
                [
                    'user_id' => $user->id,
                    'model' => 'Toyota Corolla',
                    'year' => 2020,
                    'registration_number' => 'ABC123',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => $user->id,
                    'model' => 'Honda Civic',
                    'year' => 2018,
                    'registration_number' => 'DEF456',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
