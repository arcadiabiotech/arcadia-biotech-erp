<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\TalukaController;
use App\Http\Controllers\VillageController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Dealers
    Route::resource('dealers', DealerController::class);

    // Customers
    Route::resource('customers', CustomerController::class);

    // States
    Route::resource('states', StateController::class);

    // Districts
    Route::resource('districts', DistrictController::class);

    // Talukas
    Route::resource('talukas', TalukaController::class);
    
    // Villages
   // Route::resource('villages', VillageController::class);
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';