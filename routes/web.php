<?php

use App\Http\Controllers\Auth\SsoController;
use App\Models\SuperappDepartment;
use App\Models\SuperappDivision;
use App\Models\SuperappEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;

Route::view('/', 'pages::auth.login')->name('login')->middleware('guest');
Route::get('/auth/sso', [SsoController::class, 'handle'])->middleware('guest');

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
    Route::livewire('dashboard/keys/box', 'pages::dashboard.box-keys')->name('dashboard.keys.box');

    Route::livewire('dashboard/late/manual-entry', 'pages::dashboard.late-manual-entry')->name('dashboard.late-manual-entry');
    Route::livewire('dashboard/break/manual-entry', 'pages::dashboard.break-manual-entry')->name('dashboard.break-manual-entry');
    Route::livewire('dashboard/night-shift/manual-entry', 'pages::dashboard.night-shift-manual-entry')->name('dashboard.night-shift-manual-entry');
    Route::livewire('dashboard/visitor/manual-entry', 'pages::dashboard.visitor-manual-entry')->name('dashboard.visitor-manual-entry');
    Route::livewire('dashboard/delivery/manual-entry', 'pages::dashboard.delivery-manual-entry')->name('dashboard.delivery-manual-entry');
    Route::livewire('dashboard/vehicle-pass/manual-entry', 'pages::dashboard.vehicle-pass-manual-entry')->name('dashboard.vehicle-pass-manual-entry');
    Route::livewire('dashboard/vehicle-pass/employee-pass/manual-entry', 'pages::dashboard.employee-pass-manual-entry')->name('dashboard.employee-pass-manual-entry');
    Route::livewire('dashboard/keys/vehicle/manual-entry', 'pages::dashboard.vehicle-keys-manual-entry')->name('dashboard.vehicle-keys-manual-entry');
    Route::livewire('dashboard/keys/facility/manual-entry', 'pages::dashboard.facility-keys-manual-entry')->name('dashboard.keys.facility-manual-entry');
});

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

Route::get('/departments/api', function () {
    return SuperappDepartment::all();
})->middleware('auth');

Route::get('/proxy/employee-photo', function (Request $request) {
    $path = $request->query('path');

    abort_if(!$path || str_contains($path, '..'), 400);

    $remoteUrl = 'https://superapp.ewindo.co.id/storage/' . ltrim($path, '/');
    $cacheKey  = 'emp_photo_' . md5($path);

    $imageData = Cache::remember($cacheKey, now()->addHours(6), function () use ($remoteUrl) {
        $response = Http::timeout(10)->get($remoteUrl);
        abort_if(!$response->ok(), 404);
        return $response->body();
    });

    $manager = new ImageManager(new Driver());
    $img = $manager->decodeBinary($imageData)
        ->scale(width: 300)
        ->encode(new JpegEncoder(quality: 75));

    return response($img)
        ->header('Content-Type', 'image/jpeg')
        ->header('Cache-Control', 'public, max-age=21600');
})->middleware('auth');

Route::get('/divisions/api', function () {
    return SuperappDivision::all();
})->middleware('auth');

require __DIR__ . '/settings.php';
