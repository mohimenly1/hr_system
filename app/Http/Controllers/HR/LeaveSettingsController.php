<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class LeaveSettingsController extends Controller
{
    public function index()
    {
        // Note: Add authorization logic here later
        return Inertia::render('HR/LeaveSettings/Index', [
            'leaveTypes' => LeaveType::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:leave_types,name',
            'default_balance' => 'required|integer|min:0',
            'is_paid' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        LeaveType::create($validated);
        return Redirect::back()->with('success', 'تم إنشاء نوع الإجازة بنجاح.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:leave_types,name,' . $leaveType->id,
            'default_balance' => 'required|integer|min:0',
            'is_paid' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $leaveType->update($validated);
        return Redirect::back()->with('success', 'تم تحديث نوع الإجازة بنجاح.');
    }
}
