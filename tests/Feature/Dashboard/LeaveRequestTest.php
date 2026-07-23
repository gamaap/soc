<?php

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveRequestSyncService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    // The Volt component syncs from PMS (Superapp) on mount, which the test
    // suite must never touch (SQLite in-memory has no `superapp` connection).
    // Pre-filling the throttle cache key makes mount() skip the sync call.
    Cache::put('leave-requests:synced:'.today()->toDateString(), true, 300);
});

test('hourly leave request maps period start/end time and is not full day', function () {
    $service = new LeaveRequestSyncService;

    $attributes = $service->mapRow([
        'pms_request_id' => 1,
        'pms_employee_id' => 10,
        'employee_name' => 'Jane Doe',
        'department' => 'Engineering 1',
        'leave_type' => '2-Hour Leave/Izin 2 Jam',
        'request_sub_type_id' => 2,
        'period_start_time' => '08:00:00',
        'period_end_time' => '10:00:00',
        'leaving_time' => null,
        'will_not_return' => '0',
        'reason' => 'Berobat ke dokter',
        'destination' => null,
    ], '2026-07-23');

    expect($attributes['permitted_start_time'])->toBe('08:00:00')
        ->and($attributes['permitted_end_time'])->toBe('10:00:00')
        ->and($attributes['is_full_day'])->toBeFalse()
        ->and($attributes['will_not_return'])->toBeFalse();
});

test('full day leave request has no permitted time and is marked full day', function () {
    $service = new LeaveRequestSyncService;

    $attributes = $service->mapRow([
        'pms_request_id' => 2,
        'pms_employee_id' => 11,
        'employee_name' => 'John Doe',
        'department' => 'Production 1',
        'leave_type' => 'Annual Leave/Cuti Tahunan',
        'request_sub_type_id' => 1,
        'period_start_time' => null,
        'period_end_time' => null,
        'leaving_time' => null,
        'will_not_return' => '0',
        'reason' => null,
        'destination' => null,
    ], '2026-07-23');

    expect($attributes['permitted_start_time'])->toBeNull()
        ->and($attributes['permitted_end_time'])->toBeNull()
        ->and($attributes['is_full_day'])->toBeTrue();
});

test('official travel request uses leaving time as permitted start time', function () {
    $service = new LeaveRequestSyncService;

    $attributes = $service->mapRow([
        'pms_request_id' => 3,
        'pms_employee_id' => 12,
        'employee_name' => 'Ahmad',
        'department' => 'Marketing 1',
        'leave_type' => 'Personal Official Travel/Perjalanan Dinas Pribadi',
        'request_sub_type_id' => 5,
        'period_start_time' => null,
        'period_end_time' => null,
        'leaving_time' => '09:00:00',
        'will_not_return' => '1',
        'reason' => null,
        'destination' => 'Jakarta',
    ], '2026-07-23');

    expect($attributes['permitted_start_time'])->toBe('09:00:00')
        ->and($attributes['permitted_end_time'])->toBeNull()
        ->and($attributes['is_full_day'])->toBeFalse()
        ->and($attributes['will_not_return'])->toBeTrue();
});

test('period-based leave starting at or after the 07:55 shift start auto-fills actual time', function () {
    $service = new LeaveRequestSyncService;

    $base = [
        'pms_request_id' => 5,
        'pms_employee_id' => 14,
        'employee_name' => 'Citra',
        'department' => 'Engineering 1',
        'leave_type' => '2-Hour Leave/Izin 2 Jam',
        'request_sub_type_id' => 2,
        'leaving_time' => null,
        'will_not_return' => '0',
        'reason' => null,
        'destination' => null,
    ];

    $atShiftStart = $service->mapRow([...$base, 'period_start_time' => '07:55:00', 'period_end_time' => '12:00:00'], '2026-07-23');
    $afterShiftStart = $service->mapRow([...$base, 'period_start_time' => '13:00:00', 'period_end_time' => '17:00:00'], '2026-07-23');

    expect($atShiftStart['actual_time'])->toBe('07:55:00')
        ->and($afterShiftStart['actual_time'])->toBe('13:00:00');
});

test('period-based leave starting before the 07:55 shift start does not auto-fill actual time', function () {
    $attributes = (new LeaveRequestSyncService)->mapRow([
        'pms_request_id' => 6,
        'pms_employee_id' => 15,
        'employee_name' => 'Dudung',
        'department' => 'Engineering 1',
        'leave_type' => 'Half Day Leave/Izin Setengah Hari',
        'request_sub_type_id' => 3,
        'period_start_time' => '07:50:00',
        'period_end_time' => '12:00:00',
        'leaving_time' => null,
        'will_not_return' => '0',
        'reason' => null,
        'destination' => null,
    ], '2026-07-23');

    expect($attributes)->not->toHaveKey('actual_time');
});

test('official travel (leaving time only, not period-based) does not auto-fill actual time', function () {
    $service = new LeaveRequestSyncService;

    $attributes = $service->mapRow([
        'pms_request_id' => 7,
        'pms_employee_id' => 16,
        'employee_name' => 'Ahmad',
        'department' => 'Marketing 1',
        'leave_type' => 'Personal Official Travel/Perjalanan Dinas Pribadi',
        'request_sub_type_id' => 5,
        'period_start_time' => null,
        'period_end_time' => null,
        'leaving_time' => '09:00:00',
        'will_not_return' => '1',
        'reason' => null,
        'destination' => 'Jakarta',
    ], '2026-07-23');

    expect($attributes)->not->toHaveKey('actual_time');
});

test('full day leave (annual/sick) does not auto-fill actual time', function () {
    $service = new LeaveRequestSyncService;

    $attributes = $service->mapRow([
        'pms_request_id' => 8,
        'pms_employee_id' => 17,
        'employee_name' => 'Sick Employee',
        'department' => 'Production 1',
        'leave_type' => 'Sick Leave/Izin Sakit',
        'request_sub_type_id' => 4,
        'period_start_time' => null,
        'period_end_time' => null,
        'leaving_time' => null,
        'will_not_return' => '0',
        'reason' => null,
        'destination' => null,
    ], '2026-07-23');

    expect($attributes)->not->toHaveKey('actual_time');
});

test('will_not_return string values are cast to boolean', function () {
    $service = new LeaveRequestSyncService;

    $base = [
        'pms_request_id' => 4,
        'pms_employee_id' => 13,
        'employee_name' => 'Test',
        'department' => null,
        'leave_type' => 'Half Day Leave/Izin Setengah Hari',
        'request_sub_type_id' => 3,
        'period_start_time' => '13:00:00',
        'period_end_time' => '17:00:00',
        'leaving_time' => null,
        'reason' => null,
        'destination' => null,
    ];

    expect($service->mapRow([...$base, 'will_not_return' => '1'], '2026-07-23')['will_not_return'])->toBeTrue()
        ->and($service->mapRow([...$base, 'will_not_return' => '0'], '2026-07-23')['will_not_return'])->toBeFalse();
});

test('recording actual time fills the column and does not overwrite an existing value', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = LeaveRequest::create([
        'employee_name' => 'Jane Doe',
        'department' => 'Engineering 1',
        'leave_type' => 'Half Day Leave/Izin Setengah Hari',
        'date' => today()->toDateString(),
        'permitted_start_time' => '07:55:00',
        'permitted_end_time' => '12:00:00',
    ]);

    Livewire::test('pages::dashboard.request')
        ->call('recordActualTime', $request->id);

    $request->refresh();
    expect($request->actual_time)->not->toBeNull();

    $recordedTime = $request->actual_time;

    Livewire::test('pages::dashboard.request')
        ->call('recordActualTime', $request->id);

    expect($request->refresh()->actual_time)->toBe($recordedTime);
});

test('recording actual return requires actual time and is blocked when employee will not return', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = LeaveRequest::create([
        'employee_name' => 'Jane Doe',
        'department' => 'Engineering 1',
        'leave_type' => 'Half Day Leave/Izin Setengah Hari',
        'date' => today()->toDateString(),
        'permitted_start_time' => '07:55:00',
        'permitted_end_time' => '12:00:00',
    ]);

    Livewire::test('pages::dashboard.request')
        ->call('recordActualReturn', $request->id);

    expect($request->refresh()->actual_return)->toBeNull();

    $request->update(['actual_time' => '08:00:00', 'will_not_return' => true]);

    Livewire::test('pages::dashboard.request')
        ->call('recordActualReturn', $request->id);

    expect($request->refresh()->actual_return)->toBeNull();

    $request->update(['will_not_return' => false]);

    Livewire::test('pages::dashboard.request')
        ->call('recordActualReturn', $request->id);

    expect($request->refresh()->actual_return)->not->toBeNull();
});

test('full day leave request cannot have actual time or actual return recorded', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $request = LeaveRequest::create([
        'employee_name' => 'Jane Doe',
        'department' => 'Engineering 1',
        'leave_type' => 'Annual Leave/Cuti Tahunan',
        'date' => today()->toDateString(),
        'is_full_day' => true,
    ]);

    Livewire::test('pages::dashboard.request')
        ->call('recordActualTime', $request->id)
        ->assertSee('Not Required');

    expect($request->refresh()->actual_time)->toBeNull();

    Livewire::test('pages::dashboard.request')
        ->call('recordActualReturn', $request->id);

    expect($request->refresh()->actual_return)->toBeNull();
});

test('list only shows active requests for the selected date and filters by search', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    LeaveRequest::create([
        'employee_name' => 'Jane Doe',
        'department' => 'Engineering 1',
        'leave_type' => 'Annual Leave/Cuti Tahunan',
        'date' => today()->toDateString(),
        'is_full_day' => true,
        'active' => true,
    ]);

    LeaveRequest::create([
        'employee_name' => 'Inactive Employee',
        'department' => 'Engineering 1',
        'leave_type' => 'Annual Leave/Cuti Tahunan',
        'date' => today()->toDateString(),
        'is_full_day' => true,
        'active' => false,
    ]);

    LeaveRequest::create([
        'employee_name' => 'Different Day Employee',
        'department' => 'Engineering 1',
        'leave_type' => 'Annual Leave/Cuti Tahunan',
        'date' => today()->addDay()->toDateString(),
        'is_full_day' => true,
        'active' => true,
    ]);

    Livewire::test('pages::dashboard.request')
        ->assertSee('Jane Doe')
        ->assertDontSee('Inactive Employee')
        ->assertDontSee('Different Day Employee');

    Livewire::test('pages::dashboard.request')
        ->set('search', 'jane')
        ->assertSee('Jane Doe');

    Livewire::test('pages::dashboard.request')
        ->set('search', 'nomatch')
        ->assertDontSee('Jane Doe');
});
