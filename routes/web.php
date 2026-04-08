<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');
Route::post('/dashboard/comments-read', [DashboardController::class, 'markCommentsRead'])
    ->middleware(['auth', 'verified'])->name('dashboard.comments.read');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Kunden
    Route::resource('clients', ClientController::class)->except(['show', 'create', 'edit']);

    // Kalender / Termine
    Route::get('/calendar', [AppointmentController::class, 'index'])->name('calendar.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

    // Comments
    Route::post('/appointments/{appointment}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings/company', [SettingController::class, 'updateCompany'])->name('settings.company.update');
    Route::put('/settings/calendar', [SettingController::class, 'updateCalendar'])->name('settings.calendar.update');

    // Statuses
    Route::post('/statuses', [StatusController::class, 'store'])->name('statuses.store');
    Route::put('/statuses/{status}', [StatusController::class, 'update'])->name('statuses.update');
    Route::delete('/statuses/{status}', [StatusController::class, 'destroy'])->name('statuses.destroy');
    Route::put('/statuses-reorder', [StatusController::class, 'reorder'])->name('statuses.reorder');
});

Route::middleware(['auth', 'owner'])->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employees/{user}/permissions', [EmployeeController::class, 'updatePermissions'])->name('employees.permissions.update');
    Route::delete('/employees/{user}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
});

require __DIR__.'/auth.php';
