<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\TeacherContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class TeacherContractController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'contract_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'salary_type' => 'required|in:monthly,hourly',
            'salary_amount' => 'required_if:salary_type,monthly|nullable|numeric|min:0',
            'hourly_rate' => 'required_if:salary_type,hourly|nullable|numeric|min:0',
            'working_hours_per_week' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,pending,expired,terminated',
        ]);

        TeacherContract::create($request->all());

        return Redirect::back()->with('success', 'تمت إضافة العقد الجديد بنجاح.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TeacherContract $teacherContract)
    {
        $validatedData = $request->validate([
            'contract_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'salary_type' => 'required|in:monthly,hourly',
            'salary_amount' => 'required_if:salary_type,monthly|nullable|numeric|min:0',
            'hourly_rate' => 'required_if:salary_type,hourly|nullable|numeric|min:0',
            'working_hours_per_week' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,pending,expired,terminated',
        ]);

        $teacherContract->update($validatedData);

        return Redirect::back()->with('success', 'تم تحديث بيانات العقد بنجاح.');
    }

    /**
     * Update the status of the specified resource in storage.
     */
    public function updateStatus(Request $request, TeacherContract $teacherContract)
    {
        $request->validate(['status' => 'required|in:active,expired,terminated']);
        
        $teacherContract->update(['status' => $request->status]);

        return Redirect::back()->with('success', 'تم تحديث حالة العقد بنجاح.');
    }
}
