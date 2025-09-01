<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class GradeSubjectController extends Controller
{
    /**
     * Display the subject assignment interface.
     */
    public function index()
    {
        // Get the active academic year to only show relevant grades
        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
        if (!$activeYear) {
             return Inertia::render('School/Academic/AssignSubjects/Index', [
                'grades' => [],
                'allSubjects' => [],
                'activeYear' => null,
            ]);
        }

        return Inertia::render('School/Academic/AssignSubjects/Index', [
            // Eager load the subjects relationship for each grade
            'grades' => Grade::where('academic_year_id', $activeYear->id)->with('subjects')->get(),
            'allSubjects' => Subject::all(),
            'activeYear' => $activeYear,
        ]);
    }

    /**
     * Store the subject assignments for a specific grade.
     */
    public function store(Request $request)
    {
        $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'subject_ids' => 'present|array', // 'present' ensures the key exists, even if empty
            'subject_ids.*' => 'exists:subjects,id', // Validate each ID in the array
        ]);

        $grade = Grade::find($request->input('grade_id'));

        // The sync method is perfect for managing many-to-many relationships.
        // It attaches new subjects, detaches missing ones, and leaves existing ones untouched.
        $grade->subjects()->sync($request->input('subject_ids'));

        return Redirect::back()->with('success', 'تم تحديث المقررات للمرحلة بنجاح.');
    }
}
