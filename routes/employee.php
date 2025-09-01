<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Employee\EmployeePortalController;

// Routes for the Employee Self-Service Portal
Route::middleware(['auth', 'verified', 'role:employee'])->prefix('my-portal')->name('employee.')->group(function () {
    
    // Route for viewing own payslips list
    Route::get('/my-payslips', [EmployeePortalController::class, 'myPayslips'])->name('payslips.index');

    // Route for viewing a single payslip's details
    Route::get('/my-payslips/{payslip}', [EmployeePortalController::class, 'showMyPayslip'])->name('payslips.show');

});

