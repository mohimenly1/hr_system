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
use App\Http\Controllers\Integrations\SchedulingController; // <-- إضافة الكنترولر الجديد

// A dedicated route group for all HR features, protected by auth middleware.
Route::middleware(['auth', 'verified'])->prefix('hr')->name('hr.')->group(function () {
    
    // --- Employee Management ---
    Route::resource('employees', EmployeeController::class);
    Route::post('employees/{employee}/attachments', [EmployeeController::class, 'storeAttachment'])->name('employees.attachments.store');
    Route::post('employees/{employee}/leaves', [EmployeeController::class, 'storeLeave'])->name('employees.leaves.store');
    Route::post('employees/{employee}/experiences', [EmployeeController::class, 'storeWorkExperience'])->name('employees.experiences.store');
    Route::put('employees/{employee}/experiences/{experience}', [EmployeeController::class, 'updateWorkExperience'])->name('employees.experiences.update');
    Route::delete('employees/{employee}/experiences/{experience}', [EmployeeController::class, 'destroyWorkExperience'])->name('employees.experiences.destroy');
    Route::put('employees/{employee}/personal-info', [EmployeeController::class, 'updatePersonalInfo'])->name('employees.personal-info.update');
    Route::put('employees/{employee}/fingerprint', [EmployeeController::class, 'updateFingerprintId'])->name('employees.fingerprint.update');
    // --- Contract Management (Now a full resource controller) ---
    Route::resource('contracts', ContractController::class)->except(['create', 'show', 'edit']);
    Route::put('contracts/{contract}/status', [ContractController::class, 'updateStatus'])->name('contracts.status.update');


    // --- Payroll Management ---
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/generate', [PayrollController::class, 'create'])->name('payroll.create');
    Route::post('payroll/generate', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{payslip}', [PayrollController::class, 'show'])->name('payroll.show');

    // --- Leave Management ---
    Route::resource('leaves', LeaveController::class);

    // --- Attendance Management ---
    Route::resource('attendances', AttendanceController::class);

    // --- Roles & Permissions Management ---
    Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
    
    // --- User Management ---
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        // --- Fingerprint Device Integration ---
        Route::get('fingerprint', [FingerprintDeviceController::class, 'index'])->name('fingerprint.index');
        Route::get('fingerprint/test-connection', [FingerprintDeviceController::class, 'testConnection'])->name('fingerprint.test');
        Route::post('fingerprint/sync-attendance', [FingerprintDeviceController::class, 'syncAttendance'])->name('fingerprint.sync.attendance');
        Route::post('fingerprint/sync-users', [FingerprintDeviceController::class, 'syncUsersToDevice'])->name('fingerprint.sync.users');
        Route::get('fingerprint/device-users', [FingerprintDeviceController::class, 'getDeviceUsers'])->name('fingerprint.device.users');
        Route::delete('fingerprint/clear-users', [FingerprintDeviceController::class, 'clearDeviceUsers'])->name('fingerprint.clear.users');
        Route::post('/fingerprint/sync-monthly', [FingerprintDeviceController::class, 'syncMonthly'])->name('fingerprint.sync.monthly');
        // مسار لعرض صفحة سجل حضور الموظف
Route::get('/employees/{employee}/attendance', [EmployeeController::class, 'showAttendance'])
->name('employees.attendance.show');

// مسار لتنفيذ مزامنة الحضور لموظف واحد
Route::post('/employees/{employee}/attendance/sync', [EmployeeController::class, 'syncSingleAttendance'])
->name('employees.attendance.sync');


// Routes for managing shifts and assignments

Route::resource('hr/shifts', ShiftController::class)->names('shifts');
Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.update.status');

Route::post('hr/shift-assignments', [ShiftAssignmentController::class, 'store'])->name('shift-assignments.store');

        // Intelligent Scheduling
      // Intelligent Scheduling
      Route::get('integrations/scheduling', [SchedulingController::class, 'index'])->name('integrations.scheduling.index');
      Route::post('integrations/scheduling/constraints', [SchedulingController::class, 'storeConstraints'])->name('integrations.scheduling.constraints.store');
  
      // Template Management
      Route::post('integrations/scheduling/templates', [SchedulingController::class, 'storeTemplate'])->name('integrations.scheduling.templates.store');
      Route::put('integrations/scheduling/templates/{template}', [SchedulingController::class, 'updateTemplate'])->name('integrations.scheduling.templates.update');
      Route::post('integrations/scheduling/templates/{template}/constraints', [SchedulingController::class, 'storeTemplateConstraints'])->name('integrations.scheduling.templates.constraints.store');
      Route::post('scheduling/generate', [SchedulingController::class, 'generateTimetable'])->name('integrations.scheduling.generate'); // <-- إضافة المسار الجديد
});

