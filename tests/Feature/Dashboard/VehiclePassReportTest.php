<?php

use App\Models\EmployeePass;
use App\Models\OtherPass;
use App\Models\User;
use Livewire\Livewire;

test('vehicle pass report merges employee and other pass records', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    EmployeePass::create([
        'date' => now()->toDateString(),
        'name' => 'Budi Employee',
        'department' => 'Production',
        'license_plate' => 'B 1111 AA',
        'entry_time' => '08:00:00',
        'leaving_time' => '17:00:00',
    ]);

    OtherPass::create([
        'date' => now()->toDateString(),
        'name' => 'Siti Other',
        'department' => 'Logistics',
        'license_plate' => 'B 2222 BB',
        'purpose' => 'Pickup',
        'leaving_time' => '09:00:00',
        'entry_time' => '10:00:00',
    ]);

    Livewire::test('pages::dashboard.report')
        ->set('category', 'vehicle_pass')
        ->assertSee('Budi Employee')
        ->assertSee('Siti Other')
        ->assertSee('Employee')
        ->assertSee('Other');
});

test('vehicle pass report does not error when no employee or other pass records exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::dashboard.report')
        ->set('category', 'vehicle_pass')
        ->assertOk();
});
