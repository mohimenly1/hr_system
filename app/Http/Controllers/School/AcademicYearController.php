<?php

// The namespace is now School
namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // The render path is updated to the new 'School' directory
        return Inertia::render('School/Academic/Years/Index', [
            'academic_years' => AcademicYear::latest()->get(),
        ]);
    }

    // ... store, setActive, and destroy methods remain the same ...
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:academic_years,name',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        AcademicYear::create($request->all());

        return Redirect::back()->with('success', 'تمت إضافة السنة الدراسية بنجاح.');
    }

    /**
     * Set the specified academic year as active.
     */
    public function setActive(Request $request, AcademicYear $academic_year)
    {
        DB::transaction(function () use ($academic_year) {
            // Set all other years to inactive
            AcademicYear::where('id', '!=', $academic_year->id)->update(['is_active' => false]);

            // Set the selected year to active
            $academic_year->update(['is_active' => true]);
        });
        
        return Redirect::back()->with('success', "تم تفعيل السنة الدراسية '{$academic_year->name}' بنجاح.");
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicYear $academic_year)
    {
        // Add logic here to prevent deletion if it has related records
        $academic_year->delete();

        return Redirect::back()->with('success', 'تم حذف السنة الدراسية.');
    }
}
