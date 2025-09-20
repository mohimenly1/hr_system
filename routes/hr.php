<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\ContractController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\HR\LeaveController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\RoleController;
use App\Http\Controllers\HR\UserController;
use App\Http\Controllers\HR\FingerprintDeviceController;
use App\Http\Controllers\HR\ShiftController;
use App\Http\Controllers\HR\ShiftAssignmentController;
use App\Http\Controllers\Integrations\SchedulingController;
use App\Http\Controllers\HR\DepartmentController;
use App\Http\Controllers\HR\LeaveSettingsController;
use App\Http\Controllers\HR\EvaluationSettingsController;
use App\Http\Controllers\HR\EmployeePerformanceEvaluationController;
use App\Http\Controllers\HR\PenaltySettingsController;
use App\Http\Controllers\HR\PenaltyController;

// All routes in this file are protected by the 'auth' and 'verified' middleware
// and are prefixed with '/hr' and named 'hr.'
Route::middleware(['auth', 'verified'])->prefix('hr')->name('hr.')->group(function () {

    //======================================================================
    // ADMIN & HR MANAGER CORE SETTINGS
    // These routes are for high-level configuration and user management.
    //======================================================================
    Route::middleware(['permission:manage users|manage roles|manage departments|access integrations'])->group(function () {
        
        // --- User & Role Management ---
        Route::resource('users', UserController::class)->only(['index', 'edit', 'update']);
        Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.update.status');
        Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
        Route::resource('departments', DepartmentController::class)->except(['show']);

        Route::resource('leave-settings', LeaveSettingsController::class)->only(['index', 'store', 'update']);
        Route::resource('evaluation-settings', EvaluationSettingsController::class)->only(['index', 'store', 'update']);
        Route::post('employees/{employee}/evaluations', [EmployeePerformanceEvaluationController::class, 'store'])->name('employees.evaluations.store');
        Route::resource('penalty-settings', PenaltySettingsController::class)
        ->parameters(['penalty-settings' => 'penalty_type'])
        ->only(['index', 'store', 'update']);
        Route::post('penalties', [PenaltyController::class, 'store'])->name('penalties.store');
        // --- Integrations & Advanced Settings ---
        Route::prefix('integrations')->name('integrations.')->group(function() {
            // Fingerprint Device
            Route::get('fingerprint', [FingerprintDeviceController::class, 'index'])->name('fingerprint.index');
            Route::get('fingerprint/test-connection', [FingerprintDeviceController::class, 'testConnection'])->name('fingerprint.test');
            Route::post('fingerprint/sync-attendance', [FingerprintDeviceController::class, 'syncAttendance'])->name('fingerprint.sync.attendance');
            Route::post('fingerprint/sync-users', [FingerprintDeviceController::class, 'syncUsersToDevice'])->name('fingerprint.sync.users');
            Route::get('fingerprint/device-users', [FingerprintDeviceController::class, 'getDeviceUsers'])->name('fingerprint.device.users');
            Route::delete('fingerprint/clear-users', [FingerprintDeviceController::class, 'clearDeviceUsers'])->name('fingerprint.clear.users');
            Route::post('fingerprint/sync-monthly', [FingerprintDeviceController::class, 'syncMonthly'])->name('fingerprint.sync.monthly');
            Route::post('fingerprint/backup-data', [FingerprintDeviceController::class, 'backupDeviceData'])->name('fingerprint.backup.data');
            Route::get('fingerprint/link-users', [FingerprintDeviceController::class, 'showAndLinkDeviceUsers'])->name('fingerprint.link.users');
            
            // Shifts Management
            Route::resource('shifts', ShiftController::class);
            Route::post('shift-assignments', [ShiftAssignmentController::class, 'store'])->name('shift-assignments.store');

            // Intelligent Scheduling
            Route::get('scheduling', [SchedulingController::class, 'index'])->name('scheduling.index');
            Route::post('scheduling/constraints', [SchedulingController::class, 'storeConstraints'])->name('scheduling.constraints.store');
            Route::post('scheduling/generate', [SchedulingController::class, 'generateTimetable'])->name('scheduling.generate');
            Route::post('scheduling/templates', [SchedulingController::class, 'storeTemplate'])->name('scheduling.templates.store');
            Route::put('scheduling/templates/{template}', [SchedulingController::class, 'updateTemplate'])->name('scheduling.templates.update');
            Route::post('scheduling/templates/{template}/constraints', [SchedulingController::class, 'storeTemplateConstraints'])->name('scheduling.templates.constraints.store');
        });
    });


    //======================================================================
    // EMPLOYEE MANAGEMENT (Accessible by Admins, HR, and Department Managers)
    //======================================================================
    Route::middleware(['permission:manage employees'])->group(function () {
        Route::resource('employees', EmployeeController::class);
        Route::post('employees/{employee}/attachments', [EmployeeController::class, 'storeAttachment'])->name('employees.attachments.store');
        Route::post('employees/{employee}/leaves', [EmployeeController::class, 'storeLeave'])->name('employees.leaves.store');
        Route::post('employees/{employee}/experiences', [EmployeeController::class, 'storeWorkExperience'])->name('employees.experiences.store');
        Route::put('employees/{employee}/experiences/{experience}', [EmployeeController::class, 'updateWorkExperience'])->name('employees.experiences.update');
        Route::delete('employees/{employee}/experiences/{experience}', [EmployeeController::class, 'destroyWorkExperience'])->name('employees.experiences.destroy');
        Route::put('employees/{employee}/personal-info', [EmployeeController::class, 'updatePersonalInfo'])->name('employees.personal-info.update');
        Route::put('employees/{employee}/fingerprint', [EmployeeController::class, 'updateFingerprintId'])->name('employees.fingerprint.update');
        Route::get('/employees/{employee}/attendance', [EmployeeController::class, 'showAttendance'])->name('employees.attendance.show');
        Route::post('/employees/{employee}/attendance/sync', [EmployeeController::class, 'syncSingleAttendance'])->name('employees.attendance.sync');
    });


    //======================================================================
    // OTHER HR MODULES (Each with its own permission)
    //======================================================================
    Route::resource('contracts', ContractController::class)->except(['create', 'show', 'edit'])->middleware('permission:manage contracts');
    Route::put('contracts/{contract}/status', [ContractController::class, 'updateStatus'])->name('contracts.status.update')->middleware('permission:manage contracts');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index')->middleware('permission:manage payroll');
    Route::get('payroll/generate', [PayrollController::class, 'create'])->name('payroll.create')->middleware('permission:manage payroll');
    Route::post('payroll/generate', [PayrollController::class, 'store'])->name('payroll.store')->middleware('permission:manage payroll');
    Route::get('payroll/{payslip}', [PayrollController::class, 'show'])->name('payroll.show')->middleware('permission:manage payroll');

    Route::resource('leaves', LeaveController::class)->middleware('permission:manage leaves');
    Route::resource('attendances', AttendanceController::class)->middleware('permission:manage attendance');

});

