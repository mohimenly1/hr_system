<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\School\AcademicYearController;
use App\Http\Controllers\School\GradeController;
use App\Http\Controllers\School\SectionController; // <-- Add this import
use App\Http\Controllers\School\SubjectController;
use App\Http\Controllers\School\GradeSubjectController; // <-- أضف هذا


Route::middleware(['auth', 'verified'])->prefix('school')->name('school.')->group(function () {
    
    // ... Academic Years Routes ...
    Route::resource('academic-years', AcademicYearController::class)->except(['create', 'show', 'edit', 'update']);
    Route::put('academic-years/{academic_year}/set-active', [AcademicYearController::class, 'setActive'])->name('academic-years.set-active');
    
    // --- Grades Routes ---
    Route::resource('grades', GradeController::class)->except(['create', 'show', 'edit', 'update']);

    // --- Sections Routes ---
    Route::resource('sections', SectionController::class)->only(['index', 'store', 'destroy']);
      // Subjects Management
      Route::resource('subjects', SubjectController::class)->except(['show', 'create', 'edit']);
      Route::post('grades/assign-subjects', [GradeSubjectController::class, 'store'])->name('grades.assign.subjects');


});

