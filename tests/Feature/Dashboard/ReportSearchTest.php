<?php

use App\Models\Late;
use App\Models\User;
use Livewire\Livewire;

test('searching late report records filters by name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Late::create([
        'date' => now()->toDateString(),
        'name' => 'Budi Santoso',
        'department' => 'Production',
        'actual_arrival' => '08:10:00',
        'minutes_late' => 10,
    ]);

    Late::create([
        'date' => now()->toDateString(),
        'name' => 'Siti Aminah',
        'department' => 'Logistics',
        'actual_arrival' => '08:15:00',
        'minutes_late' => 15,
    ]);

    Livewire::test('pages::dashboard.report')
        ->set('category', 'late')
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Siti Aminah');
});

test('searching late report records also matches department', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Late::create([
        'date' => now()->toDateString(),
        'name' => 'Budi Santoso',
        'department' => 'Production',
        'actual_arrival' => '08:10:00',
        'minutes_late' => 10,
    ]);

    Late::create([
        'date' => now()->toDateString(),
        'name' => 'Siti Aminah',
        'department' => 'Logistics',
        'actual_arrival' => '08:15:00',
        'minutes_late' => 15,
    ]);

    Livewire::test('pages::dashboard.report')
        ->set('category', 'late')
        ->set('search', 'Logistics')
        ->assertSee('Siti Aminah')
        ->assertDontSee('Budi Santoso');
});
