<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\School\AcademicYearController;
use App\Http\Controllers\School\GradeController;
use App\Http\Controllers\School\SectionController;
use App\Http\Controllers\School\SubjectController;
use App\Http\Controllers\School\TeacherController;
use App\Http\Controllers\School\TeacherContractController;
use App\Http\Controllers\School\GradeSubjectController;
use App\Http\Controllers\School\PerformanceEvaluationController;

Route::middleware(['auth', 'verified'])->prefix('school')->name('school.')->group(function () {
    
    // --- Academic Settings ---
    Route::resource('academic-years', AcademicYearController::class)->except(['show']);
    Route::put('academic-years/{academic_year}/set-active', [AcademicYearController::class, 'setActive'])->name('academic-years.set-active');
    Route::resource('grades', GradeController::class)->except(['show']);
    Route::post('grades/assign-subjects', [GradeController::class, 'assignSubjects'])->name('grades.assign-subjects');
    Route::resource('sections', SectionController::class)->only(['index', 'store', 'destroy']);
    Route::resource('subjects', SubjectController::class)->except(['show']);

    // --- Teacher Management ---
    Route::resource('teachers', TeacherController::class);
    Route::put('teachers/{teacher}/personal-info', [TeacherController::class, 'updatePersonalInfo'])->name('teachers.personal-info.update');
    Route::post('teachers/{teacher}/attachments', [TeacherController::class, 'storeAttachment'])->name('teachers.attachments.store');
    Route::post('teachers/{teacher}/experiences', [TeacherController::class, 'storeWorkExperience'])->name('teachers.experiences.store');
    Route::put('teachers/{teacher}/experiences/{experience}', [TeacherController::class, 'updateWorkExperience'])->name('teachers.experiences.update');
    Route::delete('teachers/{teacher}/experiences/{experience}', [TeacherController::class, 'destroyWorkExperience'])->name('teachers.experiences.destroy');
    // --- NEW ROUTE FOR ASSIGNMENTS ---
    Route::put('teachers/{teacher}/assignments', [TeacherController::class, 'updateAssignments'])->name('teachers.assignments.update');
    Route::post('teachers/{teacher}/leaves', [TeacherController::class, 'storeLeave'])->name('teachers.leaves.store');
  
      // Subjects Management
      // Route::resource('subjects', SubjectController::class)->except(['show', 'create', 'edit']);
      Route::post('grades/assign-subjects', [GradeSubjectController::class, 'store'])->name('grades.assign.subjects');
    // --- Teacher Contract Management ---
    Route::resource('teacher-contracts', TeacherContractController::class)->except(['create', 'show', 'edit', 'index']);
    Route::put('teacher-contracts/{teacherContract}/status', [TeacherContractController::class, 'updateStatus'])->name('teacher-contracts.status.update');
    Route::put('teachers/{teacher}/fingerprint', [TeacherController::class, 'updateFingerprintId'])->name('teachers.fingerprint.update');
// مسار لعرض صفحة سجل حضور المعلم
Route::get('/teachers/{teacher}/attendance', [TeacherController::class, 'showAttendance'])
    ->name('teachers.attendance.show');

// مسار لتنفيذ مزامنة الحضور لمعلم واحد
Route::post('/teachers/{teacher}/attendance/sync', [TeacherController::class, 'syncSingleAttendance'])
    ->name('teachers.attendance.sync');

    Route::post('teachers/{teacher}/evaluations', [PerformanceEvaluationController::class, 'store'])->name('evaluations.store');
});

