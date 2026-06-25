<?php

use App\Models\User;
use App\Models\Visitor;
use Livewire\Livewire;

test('recording visitor exit without items only records exit time', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $visitor = Visitor::create([
        'name' => 'Guest One',
        'visiting' => 'Staff One',
        'purpose' => 'Meeting',
        'date' => now()->toDateString(),
        'entry_time' => '08:00:00',
    ]);

    Livewire::test('pages::dashboard.visitor')
        ->call('openExitModal', $visitor->id)
        ->set('exitHasItems', '0')
        ->call('confirmExit');

    $visitor->refresh();

    expect($visitor->exit_time)->not->toBeNull();
    expect($visitor->items()->count())->toBe(0);
});

test('recording visitor exit with items records exit time and items', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $visitor = Visitor::create([
        'name' => 'Guest Two',
        'visiting' => 'Staff Two',
        'purpose' => 'Delivery pickup',
        'date' => now()->toDateString(),
        'entry_time' => '08:00:00',
    ]);

    Livewire::test('pages::dashboard.visitor')
        ->call('openExitModal', $visitor->id)
        ->set('exitHasItems', '1')
        ->set('exitItems.0.item_name', 'Laptop bag')
        ->set('exitItems.0.quantity', 1)
        ->set('exitItems.0.uom', 'Pcs')
        ->call('confirmExit');

    $visitor->refresh();

    expect($visitor->exit_time)->not->toBeNull();
    expect($visitor->items()->count())->toBe(1);
    expect($visitor->items->first()->item_name)->toBe('Laptop bag');
});
