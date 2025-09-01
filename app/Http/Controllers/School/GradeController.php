<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use App\Models\Subject;

class GradeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
  // ...
  public function index()
  {
      $activeYear = AcademicYear::where('is_active', true)->first();
      $grades = [];
  
      if ($activeYear) {
          $grades = Grade::where('academic_year_id', $activeYear->id)
                        ->with('subjects') // ⬅️ Add this line to eager load the subjects
                        ->withCount('subjects')
                        ->latest()
                        ->get();
      }
  
      // You also need to pass all subjects to the view for the modal
      $allSubjects = Subject::all(); // ⬅️ Add this line
  
      return Inertia::render('School/Academic/Grades/Index', [
          'grades' => $grades,
          'activeYear' => $activeYear,
          'allSubjects' => $allSubjects, // ⬅️ Pass this to the frontend
      ]);
  }
// ...

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            return Redirect::back()->with('error', 'يجب تفعيل سنة دراسية أولاً قبل إضافة المراحل.');
        }

        $activeYear->grades()->create($request->all());

        return Redirect::back()->with('success', 'تمت إضافة المرحلة الدراسية بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Grade $grade)
    {
        // Add logic here to prevent deletion if it has related sections/students
        $grade->delete();
        return Redirect::back()->with('success', 'تم حذف المرحلة الدراسية.');
    }
}
