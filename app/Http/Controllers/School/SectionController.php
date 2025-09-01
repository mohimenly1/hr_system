<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Grade;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $grades = [];

        if ($activeYear) {
            // Eager load the sections for each grade
            $grades = Grade::where('academic_year_id', $activeYear->id)
                ->with('sections')
                ->latest()
                ->get();
        }

        return Inertia::render('School/Academic/Sections/Index', [
            'grades' => $grades,
            'activeYear' => $activeYear,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'grade_id' => 'required|exists:grades,id',
        ]);

        Section::create($request->all());

        return Redirect::back()->with('success', 'تمت إضافة الشعبة بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section)
    {
        // Add logic to prevent deletion if it contains students
        $section->delete();
        return Redirect::back()->with('success', 'تم حذف الشعبة.');
    }
}
