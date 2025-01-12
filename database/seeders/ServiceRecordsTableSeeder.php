<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceRecordsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Fetch all vehicles belonging to demo@example.com
        $vehicles = DB::table('vehicles')
            ->join('users', 'vehicles.user_id', '=', 'users.id')
            ->where('users.email', 'demo@example.com')
            ->select('vehicles.id')
            ->get();

        if ($vehicles->isNotEmpty()) {
            DB::table('service_records')->insert([
                [
                    'vehicle_id' => $vehicles[0]->id, // First vehicle
                    'service_date' => '2023-01-15',
                    'service_place' => 'Toyota Service Center',
                    'service_cost' => 500.00,
                    'description' => 'Oil change and brake check',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'vehicle_id' => $vehicles[0]->id, // First vehicle
                    'service_date' => '2023-07-10',
                    'service_place' => 'QuickFix Garage',
                    'service_cost' => 300.00,
                    'description' => 'Tire rotation and alignment',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'vehicle_id' => $vehicles[1]->id, // Second vehicle
                    'service_date' => '2023-05-20',
                    'service_place' => 'Honda Service Center',
                    'service_cost' => 450.00,
                    'description' => 'Engine tuning and air filter replacement',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
