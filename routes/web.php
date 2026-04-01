<?php

<<<<<<< HEAD
=======
use App\Models\SuperappDepartment;
use App\Models\SuperappDivision;
use App\Models\SuperappEmployee;
use Illuminate\Http\Request;
>>>>>>> 218e14397ddbd6d3595a575c996a38f5b38bfd24
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages::auth.login')->name('login')->middleware('guest');

Route::middleware(['auth'])->group(function () {
    Route::livewire('dashboard/request', 'pages::dashboard.request')->name('dashboard.request');
    Route::livewire('dashboard/late', 'pages::dashboard.late')->name('dashboard.late');
    Route::livewire('dashboard/break', 'pages::dashboard.break')->name('dashboard.break');
    Route::livewire('dashboard/night-shift', 'pages::dashboard.night-shift')->name('dashboard.night-shift');
    Route::livewire('dashboard/visitor', 'pages::dashboard.visitor')->name('dashboard.visitor');
    Route::livewire('dashboard/delivery', 'pages::dashboard.delivery')->name('dashboard.delivery');
    Route::livewire('dashboard/vehicle-pass', 'pages::dashboard.vehicle-pass')->name('dashboard.vehicle-pass');
    Route::livewire('dashboard/keys/vehicle', 'pages::dashboard.vehicle-keys')->name('dashboard.keys.vehicle');
    Route::livewire('dashboard/keys/facility', 'pages::dashboard.facility-keys')->name('dashboard.keys.facility');
});

<<<<<<< HEAD
require __DIR__.'/settings.php';
=======
Route::get('/employees/api', function (Request $request) {
    $search = $request->query('search');

    return SuperappEmployee::where('is_delete', '=', false)
        ->where('is_active', '=', true)
        ->where('plant_id', '=', 1)
        ->when($search, function ($query) use ($search) {
            $query->whereRaw(
                'LOWER(fullname) LIKE ?', 
                ['%' . strtolower($search) . '%']
            );
        })
        ->orderBy('fullname')
        ->with([
            'department:id,name',
            'division:id,name'
        ])
        ->get([
            'id',
            'fullname',
            'username',
            'departement_id',
            'division_id',
            'employee_id',
            'nik',
            'photo'
        ]);
})->middleware('auth');

Route::get('/employee-master/api', function (Request $request) {
    $search = $request->query('search');

    $models = App\Models\EmployeeMasterPass::query()
        ->when($search, function ($query) use ($search) {
            $query->where(function ($sub) use ($search) {
                $sub->where('employee_name', 'ILIKE', "%{$search}%")
                    ->orWhere('license_plate', 'ILIKE', "%{$search}%");
            });
        })
        ->get();

    return $models->groupBy('employee_name')->map(function ($group, $name) {
        return [
            'employee_name' => $name,
            'department' => $group->first()->department,
            'license_plates' => $group->pluck('license_plate')->unique()->values()->toArray(),
        ];
    })->values();
})->middleware('auth');

Route::get('/departments/api', function() {
    return SuperappDepartment::all();
})->middleware('auth');

Route::get('/divisions/api', function() {
    return SuperappDivision::all();
})->middleware('auth');

require __DIR__.'/settings.php';
>>>>>>> 218e14397ddbd6d3595a575c996a38f5b38bfd24
