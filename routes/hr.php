<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\ContractController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\HR\LeaveController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\RoleController;
use App\Http\Controllers\HR\UserController;

// A dedicated route group for all HR features, protected by auth middleware.
Route::middleware(['auth', 'verified'])->prefix('hr')->name('hr.')->group(function () {
    
    // --- Employee Management ---
    // Handles index, create, store, show, edit, update for employees.
    Route::resource('employees', EmployeeController::class);
    // Custom route for adding attachments to a specific employee.
    Route::post('employees/{employee}/attachments', [EmployeeController::class, 'storeAttachment'])->name('employees.attachments.store');

    // --- Contract Management ---
    // Since contracts are created with employees, we only need an index to view all contracts.
    Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');

    // --- Payroll Management ---
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/generate', [PayrollController::class, 'create'])->name('payroll.create');
    Route::post('payroll/generate', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{payslip}', [PayrollController::class, 'show'])->name('payroll.show');

    // --- Leave Management ---
    Route::resource('leaves', LeaveController::class);
    Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');

    // --- Attendance Management ---
    Route::resource('attendances', AttendanceController::class);

    // --- Roles & Permissions Management ---
    Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
    
    // --- User Management ---
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

});

