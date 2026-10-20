<?php

declare(strict_types=1);

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReminderController;
use Illuminate\Support\Facades\Route;

// o starter kit usa a rota home nos layouts de login
Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::resource('clients', ClientController::class)->except('show');
    Route::resource('invoices', InvoiceController::class)->except('show');
    Route::get('reminders', ReminderController::class)->name('reminders.index');
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
});
