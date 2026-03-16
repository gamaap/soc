<?php

namespace Database\Seeders;

use App\Models\VehiclePass;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehiclePassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VehiclePass::create([
            'name' => 'Budi Santoso',
            'license_plate' => 'B 1234 XYZ',
            'purpose' => 'Escort',
            'destination' => 'Jl. Industri No. 15 Bekasi',
            'date' => now()->toDateString()
        ]);

        VehiclePass::create([
            'name' => 'Andi Pratama',
            'license_plate' => 'B 8877 ABC',
            'purpose' => 'Delivery',
            'destination' => 'Karawang Industrial Area',
            'date' => now()->toDateString()
        ]);
    }
}
