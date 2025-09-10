<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\ShiftAssignment;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'shiftable_id' => 'required|integer',
            'shiftable_type' => 'required|string',
        ]);

        ShiftAssignment::updateOrCreate(
            [
                'shiftable_id' => $request->shiftable_id,
                'shiftable_type' => $request->shiftable_type,
            ],
            [
                'shift_id' => $request->shift_id,
            ]
        );

        return redirect()->back()->with('success', 'تم تعيين الدوام بنجاح.');
    }
}
