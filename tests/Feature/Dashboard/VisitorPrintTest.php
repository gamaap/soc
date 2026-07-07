<?php

use App\Models\User;
use App\Models\Visitor;
use Livewire\Livewire;

test('visitor print page shows visitor data and hides exit time', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $visitor = Visitor::create([
        'name' => 'Guest One',
        'company' => 'Acme Corp',
        'visiting' => 'Staff One',
        'visiting_section' => 'IT Department',
        'license_plate' => 'B 1234 XYZ',
        'card_number' => 42,
        'purpose' => 'Meeting',
        'date' => now()->toDateString(),
        'entry_time' => '08:00:00',
        'exit_time' => '10:00:00',
    ]);

    $response = $this->get(route('dashboard.visitor.print', $visitor));

    $response->assertOk();
    $response->assertSee('Formulir Penerimaan Tamu');
    $response->assertSee($visitor->name);
    $response->assertSee($visitor->company);
    $response->assertSee($visitor->visiting);
    $response->assertSee($visitor->visiting_section);
    $response->assertSee('Tanggal dan Waktu Keluar');
    $response->assertDontSee('10:00');
});

test('visitor print page requires authentication', function () {
    $visitor = Visitor::create([
        'name' => 'Guest Two',
        'visiting' => 'Staff Two',
        'purpose' => 'Delivery pickup',
        'date' => now()->toDateString(),
        'entry_time' => '08:00:00',
    ]);

    $response = $this->get(route('dashboard.visitor.print', $visitor));

    $response->assertRedirect(route('login'));
});

test('saving a visitor entry stores the visiting section', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::dashboard.visitor')
        ->set('name', 'Guest Three')
        ->set('visiting', 'Staff Three')
        ->set('visiting_section', 'Finance Department')
        ->set('purpose', 'Meeting')
        ->call('save');

    $visitor = Visitor::where('name', 'Guest Three')->firstOrFail();

    expect($visitor->visiting_section)->toBe('Finance Department');

    $response = $this->get(route('dashboard.visitor.print', $visitor));

    $response->assertOk();
    $response->assertSee('Finance Department');
});
