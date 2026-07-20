<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\FarmerController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\TalukaController;
use App\Http\Controllers\VillageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\DealerAssignmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\VarietyStockController;
use App\Http\Controllers\NotificationController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Dealers (DealerPolicy enforces per-record rules — who can view/edit
    // depends on role + assignment + permission; only Admin/Super Admin can
    // ever create or delete, which the role middleware below gates coarsely
    // before the request even reaches the controller).
    Route::post('dealers/{dealer}/restore', [DealerController::class, 'restore'])
        ->name('dealers.restore')
        ->withTrashed()
        ->middleware('role:super-admin,admin');
    // Static paths (create) must be registered before the {dealer} wildcard
    // routes (show/edit/update) below, or "/dealers/create" gets swallowed
    // by "/dealers/{dealer}" and 404s instead of resolving to the create form.
    Route::resource('dealers', DealerController::class)->only(['create', 'store', 'destroy'])->middleware('role:super-admin,admin');
    Route::resource('dealers', DealerController::class)->except(['create', 'store', 'destroy']);

    // Farmers (FarmerPolicy enforces per-record rules — Marketing is scoped
    // through their assigned dealers, Dealer-role users to their own
    // dealer_id, Accounts can view/edit but never delete; only Admin/Super
    // Admin can ever create or delete, gated coarsely below).
    Route::post('farmers/{farmer}/restore', [FarmerController::class, 'restore'])
        ->name('farmers.restore')
        ->withTrashed()
        ->middleware('role:super-admin,admin');
    // Static paths (create) must precede the {farmer} wildcard routes below,
    // or "/farmers/create" gets swallowed by "/farmers/{farmer}".
    Route::resource('farmers', FarmerController::class)->only(['create', 'store', 'destroy'])->middleware('role:super-admin,admin');
    Route::resource('farmers', FarmerController::class)->except(['create', 'store', 'destroy']);

    // Bookings (BookingPolicy enforces per-record rules — Marketing/Accounts
    // can create and edit Draft bookings; only Admin/Super Admin can
    // approve, reject, hold, unlock or delete, gated coarsely below).
    // Static paths and the {booking}/action sub-routes must precede the
    // bare {booking} wildcard routes (show/edit/update) below.
    Route::resource('bookings', BookingController::class)->only(['create', 'store'])->middleware('role:super-admin,admin,marketing,accounts');
    Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
    Route::post('bookings/{booking}/restore', [BookingController::class, 'restore'])->name('bookings.restore')->withTrashed()->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/submit', [BookingController::class, 'submit'])->name('bookings.submit');
    Route::post('bookings/{booking}/verify', [BookingController::class, 'verify'])->name('bookings.verify')->middleware('role:super-admin,admin,accounts');
    Route::post('bookings/{booking}/approve', [BookingController::class, 'approve'])->name('bookings.approve')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/reject', [BookingController::class, 'reject'])->name('bookings.reject')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/hold', [BookingController::class, 'hold'])->name('bookings.hold')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/unlock', [BookingController::class, 'unlock'])->name('bookings.unlock')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/complete-dispatch', [BookingController::class, 'completeDispatch'])->name('bookings.complete-dispatch')->middleware('role:super-admin,admin');
    Route::post('bookings/{booking}/receive-payment', [BookingController::class, 'receivePayment'])->name('bookings.receive-payment')->middleware('role:super-admin,admin,accounts');
    Route::get('bookings/{booking}/print', [BookingController::class, 'print'])->name('bookings.print');
    Route::get('bookings/{booking}/pdf', [BookingController::class, 'pdf'])->name('bookings.pdf');
    Route::resource('bookings', BookingController::class)->only(['destroy'])->middleware('role:super-admin,admin');
    Route::resource('bookings', BookingController::class)->except(['create', 'store', 'destroy']);

    // Variety Stock (the "actual stock" side of the Stock Reservation engine).
    Route::get('variety-stocks', [VarietyStockController::class, 'index'])->name('variety-stocks.index')->middleware('role:super-admin,admin');
    Route::put('variety-stocks/{varietyStock}', [VarietyStockController::class, 'update'])->name('variety-stocks.update')->middleware('role:super-admin,admin');

    // Notifications (Approval Engine's "Approval Notification" feature).
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // States
    Route::resource('states', StateController::class);

    // Districts
    Route::resource('districts', DistrictController::class);

    // Talukas
    Route::resource('talukas', TalukaController::class);

    // Users (role middleware is a coarse first gate; UserPolicy enforces the
    // fine-grained per-record rules, including that Admins cannot manage
    // Super Admin accounts).
    Route::post('users/{user}/restore', [UserController::class, 'restore'])
        ->name('users.restore')
        ->withTrashed()
        ->middleware('role:super-admin,admin');
    Route::resource('users', UserController::class)->except('show')->middleware('role:super-admin,admin');

    Route::resource('roles', RoleController::class)->except('show')->middleware('role:super-admin,admin');
    Route::resource('permissions', PermissionController::class)->except('show')->middleware('permission:permissions.manage');

    // Dealer Assignments
    // Admin + Marketing can view the list (Marketing only sees its own assigned dealers).
    Route::get('dealer-assignments', [DealerAssignmentController::class, 'index'])
        ->name('dealer-assignments.index')
        ->middleware('role:super-admin,admin,marketing');
    // Only Admins can create / edit / delete assignments.
    Route::resource('dealer-assignments', DealerAssignmentController::class)
        ->except('index', 'show')
        ->middleware('role:super-admin,admin');
    
    // Villages
   // Route::resource('villages', VillageController::class);
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
