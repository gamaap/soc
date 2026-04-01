<?php

use App\Models\KeyBorrowing;
use App\Models\User;
use App\Models\VehicleKey;
use Livewire\Livewire;

test('partial vehicle key borrowing shows both borrow and return actions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $vehicleKey = VehicleKey::create([
        'vehicle_number' => 'B 1234 XYZ',
        'vehicle_type' => 'Toyota Avanza',
        'total_keys' => 2,
        'created_by' => $user->id,
    ]);

    KeyBorrowing::create([
        'vehicle_key_id' => $vehicleKey->id,
        'borrower_name' => 'John Doe',
        'borrower_department' => 'Operations',
        'borrowed_at' => now(),
        'borrow_recorded_by' => $user->id,
    ]);

    expect($vehicleKey->refresh()->available)->toBe(1);

    Livewire::test('pages::dashboard.vehicle-keys')
        ->assertSee('Partially Borrowed')
        ->assertSee('Borrow')
        ->assertSee('Return');
});

test('cannot borrow when no vehicle keys are available', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $vehicleKey = VehicleKey::create([
        'vehicle_number' => 'B 4321 XYZ',
        'vehicle_type' => 'Honda CRV',
        'total_keys' => 1,
        'created_by' => $user->id,
    ]);

    KeyBorrowing::create([
        'vehicle_key_id' => $vehicleKey->id,
        'borrower_name' => 'Jane Doe',
        'borrower_department' => 'Logistics',
        'borrowed_at' => now(),
        'borrow_recorded_by' => $user->id,
    ]);

    Livewire::test('pages::dashboard.vehicle-keys')
        ->set('keyId', $vehicleKey->id)
        ->set('borrower_name', 'John Smith')
        ->set('borrower_department', 'Support')
        ->call('borrowKey')
        ->assertHasErrors(['borrower_name']);

    expect(KeyBorrowing::where('vehicle_key_id', $vehicleKey->id)->count())->toBe(1);
});

test('can borrow another key when available exists', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $vehicleKey = VehicleKey::create([
        'vehicle_number' => 'B 9999 XYZ',
        'vehicle_type' => 'Toyota Kijang',
        'total_keys' => 2,
        'created_by' => $user->id,
    ]);

    KeyBorrowing::create([
        'vehicle_key_id' => $vehicleKey->id,
        'borrower_name' => 'Budi',
        'borrower_department' => 'Maintenance',
        'borrowed_at' => now(),
        'borrow_recorded_by' => $user->id,
    ]);

    Livewire::test('pages::dashboard.vehicle-keys')
        ->set('keyId', $vehicleKey->id)
        ->set('borrower_name', 'Joko')
        ->set('borrower_department', 'Administration')
        ->call('borrowKey')
        ->assertHasNoErrors();

    expect(KeyBorrowing::where('vehicle_key_id', $vehicleKey->id)->count())->toBe(2);
});
