<?php

use App\Models\Late;
use App\Models\User;
use Livewire\Livewire;

function createLateRecords(int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        Late::create([
            'date' => now()->toDateString(),
            'name' => "Employee {$i}",
            'department' => 'Production',
            'actual_arrival' => '08:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00',
            'minutes_late' => 10,
        ]);
    }
}

test('late report records are paginated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    createLateRecords(20);

    $component = Livewire::test('pages::dashboard.report')
        ->set('category', 'late');

    expect($component->instance()->paginatedRecords())->toHaveCount(15);

    $component->call('gotoPage', 2);

    expect($component->instance()->paginatedRecords())->toHaveCount(5);
});

test('changing category resets the report page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    createLateRecords(20);

    Livewire::test('pages::dashboard.report')
        ->set('category', 'late')
        ->call('gotoPage', 2)
        ->set('category', 'break')
        ->assertSet('paginators.page', 1);
});
