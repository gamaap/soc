<?php

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

require __DIR__.'/settings.php';
