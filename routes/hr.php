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
use App\Http\Controllers\HR\DeductionRulesController;
use App\Http\Controllers\HR\GeneralAttendanceReportController;

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

        // Deduction Rules (معادلات الخصم)
        Route::resource('deduction-rules', DeductionRulesController::class);
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
            Route::delete('shift-assignments', [ShiftAssignmentController::class, 'destroy'])->name('shift-assignments.destroy');

            // Intelligent Scheduling
            Route::get('scheduling', [SchedulingController::class, 'index'])->name('scheduling.index');
            Route::post('scheduling/constraints', [SchedulingController::class, 'storeConstraints'])->name('scheduling.constraints.store');
            Route::delete('scheduling/constraints', [SchedulingController::class, 'deleteConstraints'])->name('scheduling.constraints.delete');
            Route::post('scheduling/generate', [SchedulingController::class, 'generateTimetable'])->name('scheduling.generate');
            Route::post('scheduling/templates', [SchedulingController::class, 'storeTemplate'])->name('scheduling.templates.store');
            Route::put('scheduling/templates/{template}', [SchedulingController::class, 'updateTemplate'])->name('scheduling.templates.update');
            Route::post('scheduling/templates/{template}/constraints', [SchedulingController::class, 'storeTemplateConstraints'])->name('scheduling.templates.constraints.store');

            // Drag and Drop Timetable Management
            Route::post('scheduling/timetable-entries', [SchedulingController::class, 'storeTimetableEntry'])->name('scheduling.timetable-entries.store');
            Route::put('scheduling/timetable-entries/{timetableEntry}', [SchedulingController::class, 'updateTimetableEntry'])->name('scheduling.timetable-entries.update');
            Route::delete('scheduling/timetable-entries/{timetableEntry}', [SchedulingController::class, 'deleteTimetableEntry'])->name('scheduling.timetable-entries.delete');
            Route::post('scheduling/timetable-entries/bulk-update', [SchedulingController::class, 'bulkUpdateTimetableEntries'])->name('scheduling.timetable-entries.bulk-update');

            // Custom Hours Management
            Route::post('scheduling/custom-hours', [SchedulingController::class, 'storeCustomHours'])->name('scheduling.custom-hours.store');
            Route::get('scheduling/custom-hours', [SchedulingController::class, 'getCustomHours'])->name('scheduling.custom-hours.get');

            // Overtime Management
            Route::post('scheduling/overtime', [SchedulingController::class, 'storeOvertime'])->name('scheduling.overtime.store');
            Route::get('scheduling/overtime', [SchedulingController::class, 'getOvertime'])->name('scheduling.overtime.get');
            Route::put('scheduling/overtime/{id}', [SchedulingController::class, 'updateOvertime'])->name('scheduling.overtime.update');
            Route::delete('scheduling/overtime/{id}', [SchedulingController::class, 'deleteOvertime'])->name('scheduling.overtime.delete');
            Route::get('scheduling/default-shift-settings', [SchedulingController::class, 'getDefaultShiftSettings'])->name('scheduling.default-shift-settings.get');
            Route::post('scheduling/default-shift-settings', [SchedulingController::class, 'storeDefaultShiftSettings'])->name('scheduling.default-shift-settings.store');
            Route::put('scheduling/default-shift-settings/{defaultShiftSetting}', [SchedulingController::class, 'updateDefaultShiftSettings'])->name('scheduling.default-shift-settings.update');
            Route::delete('scheduling/default-shift-settings', [SchedulingController::class, 'deleteDefaultShiftSettings'])->name('scheduling.default-shift-settings.delete');
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
        Route::get('/employees/{employee}/attendance/export', [EmployeeController::class, 'exportAttendanceReport'])->name('employees.attendance.export');
        Route::get('/employees/{employee}/attendance/export-absent', [EmployeeController::class, 'exportAbsentDaysReport'])->name('employees.attendance.export-absent');
    });


    //======================================================================
    // OTHER HR MODULES (Each with its own permission)
    //======================================================================
    Route::resource('contracts', ContractController::class)->except(['create', 'show', 'edit'])->middleware('permission:manage contracts');
    Route::put('contracts/{contract}/status', [ContractController::class, 'updateStatus'])->name('contracts.status.update')->middleware('permission:manage contracts');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index')->middleware('permission:manage payroll');
    Route::get('payroll/generate', [PayrollController::class, 'create'])->name('payroll.create')->middleware('permission:manage payroll');
    Route::post('payroll/generate', [PayrollController::class, 'store'])->name('payroll.store')->middleware('permission:manage payroll');
    Route::get('payroll/process', [PayrollController::class, 'process'])->name('payroll.process')->middleware('permission:manage payroll');
    Route::get('payroll/process/personnel', [PayrollController::class, 'getPersonnel'])->name('payroll.process.personnel')->middleware('permission:manage payroll');
    Route::match(['get', 'post'], 'payroll/process/preview', [PayrollController::class, 'preview'])->name('payroll.process.preview')->middleware('permission:manage payroll');
    Route::match(['get', 'post'], 'payroll/process/preview/export', [PayrollController::class, 'exportPreview'])->name('payroll.process.preview.export')->middleware('permission:manage payroll');
    Route::post('payroll/process', [PayrollController::class, 'processStore'])->name('payroll.process.store')->middleware('permission:manage payroll');
    Route::get('payroll/expenses', [PayrollController::class, 'expenses'])->name('payroll.expenses')->middleware('permission:manage payroll');
    Route::get('payroll/expenses/{expense}', [PayrollController::class, 'showExpense'])->name('payroll.expenses.show')->middleware('permission:manage payroll');
    Route::get('payroll/{payslip}', [PayrollController::class, 'show'])->name('payroll.show')->middleware('permission:manage payroll');
    Route::delete('payroll/{payslip}', [PayrollController::class, 'destroy'])->name('payroll.destroy')->middleware('permission:manage payroll');
    Route::post('payroll/{payslip}/restore', [PayrollController::class, 'restore'])->name('payroll.restore')->middleware('permission:manage payroll');
    Route::delete('payroll/{payslip}/force', [PayrollController::class, 'forceDelete'])->name('payroll.force-delete')->middleware('permission:manage payroll');

    Route::resource('leaves', LeaveController::class)->middleware('permission:manage leaves');
    Route::resource('attendances', AttendanceController::class)->middleware('permission:manage attendance');

    // General Attendance Reports
    Route::get('general-attendance-report', [GeneralAttendanceReportController::class, 'index'])
        ->name('general-attendance-report.index')
        ->middleware('permission:manage attendance');
    Route::get('general-attendance-report/export', [GeneralAttendanceReportController::class, 'export'])
        ->name('general-attendance-report.export')
        ->middleware('permission:manage attendance');

    // Attendance Reports Dashboard
    Route::get('attendance-reports', [\App\Http\Controllers\HR\AttendanceReportController::class, 'index'])
        ->name('attendance-reports.index')
        ->middleware('permission:manage attendance');
    Route::get('attendance-reports/department/{department}', [\App\Http\Controllers\HR\AttendanceReportController::class, 'showDepartment'])
        ->name('attendance-reports.department')
        ->middleware('permission:manage attendance');
    Route::get('attendance-reports/{personType}/{personId}', [\App\Http\Controllers\HR\AttendanceReportController::class, 'showPersonnelDetails'])
        ->name('attendance-reports.personnel-details')
        ->middleware('permission:manage attendance');
    Route::get('attendance-reports/{personType}/{personId}/export-deductions', [\App\Http\Controllers\HR\AttendanceReportController::class, 'exportDeductionRules'])
        ->name('attendance-reports.export-deductions')
        ->middleware('permission:manage attendance');

});

