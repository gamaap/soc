<?php

use App\Models\Delivery;
use App\Models\User;
use Livewire\Livewire;

test('recording delivery exit without items only records exit time', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $delivery = Delivery::create([
        'name' => 'John Driver',
        'visiting' => 'Jane Staff',
        'purpose' => 'Delivering goods',
        'date' => now()->toDateString(),
        'entry_time' => '08:00:00',
    ]);

    Livewire::test('pages::dashboard.delivery')
        ->call('openExitModal', $delivery->id)
        ->set('exitHasItems', '0')
        ->call('confirmExit');

    $delivery->refresh();

    expect($delivery->exit_time)->not->toBeNull();
    expect($delivery->items()->where('direction', 'out')->count())->toBe(0);
});

test('recording delivery exit with items records exit time and exit items', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $delivery = Delivery::create([
        'name' => 'John Driver',
        'visiting' => 'Jane Staff',
        'purpose' => 'Delivering goods',
        'date' => now()->toDateString(),
        'entry_time' => '08:00:00',
    ]);

    $delivery->items()->create([
        'item_name' => 'Box of cables',
        'quantity' => 2,
        'uom' => 'Box',
        'direction' => 'in',
    ]);

    Livewire::test('pages::dashboard.delivery')
        ->call('openExitModal', $delivery->id)
        ->set('exitHasItems', '1')
        ->set('exitItems.0.item_name', 'Used pallet')
        ->set('exitItems.0.quantity', 1)
        ->set('exitItems.0.uom', 'Pcs')
        ->call('confirmExit');

    $delivery->refresh();

    expect($delivery->exit_time)->not->toBeNull();
    expect($delivery->items()->where('direction', 'in')->count())->toBe(1);
    expect($delivery->items()->where('direction', 'out')->count())->toBe(1);
    expect($delivery->exitItems->first()->item_name)->toBe('Used pallet');
});
