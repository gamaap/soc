<?php

namespace Database\Seeders;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LeaveRequest::create([
            'employee_name' => 'Siti Supriyati',
            'department' => 'Engineering',
            'leave_type' => 'two hour leave',
            'purpose' => 'Doctor Appointment',
            'date' => now()->toDateString(),
            'permitted_start_time' => Carbon::createFromTime(8, 0),
            'permitted_end_time' => Carbon::createFromTime(10, 0)
        ]);

        LeaveRequest::create([
            'employee_name' => 'Andri Hendri',
            'department' => 'Engineering',
            'leave_type' => 'half day hour leave',
            'purpose' => 'Take children to school',
            'date' => now()->toDateString(),
            'permitted_start_time' => Carbon::createFromTime(8, 0),
            'permitted_end_time' => Carbon::createFromTime(12, 0),
        ]);

        LeaveRequest::create([
            'employee_name' => 'Sugih',
            'department' => 'Marketing',
            'leave_type' => 'official travel',
            'purpose' => 'Customer Visit',
            'date' => now()->toDateString(),
            'permitted_start_time' => Carbon::createFromTime(8, 0),
        ]);
    }
}
