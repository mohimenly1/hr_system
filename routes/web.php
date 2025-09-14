<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;

// Route::get('/', function () {
//     return Inertia::render('Welcome');
// })->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


    


require __DIR__.'/hr.php';
require __DIR__.'/school.php';
require __DIR__.'/employee.php';
require __DIR__.'/settings.php';
require __DIR__.'/documents.php';
require __DIR__.'/auth.php';
