<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Penalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PenaltyController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'penalizable_id' => 'required|integer',
            'penalizable_type' => 'required|string|in:App\Models\Employee,App\Models\Teacher',
            'penalty_type_id' => 'required|exists:penalty_types,id',
            'reason' => 'required|string',
            'issued_at' => 'required|date',
        ]);

        $modelClass = $validated['penalizable_type'];
        $penalizable = $modelClass::findOrFail($validated['penalizable_id']);

        $this->authorize('create', [Penalty::class, $penalizable]);

        $penalizable->penalties()->create([
            'penalty_type_id' => $validated['penalty_type_id'],
            'reason' => $validated['reason'],
            'issued_at' => $validated['issued_at'],
            'issued_by_user_id' => Auth::id(),
        ]);

        $routeName = ($penalizable instanceof \App\Models\Employee) 
            ? 'hr.employees.show' 
            : 'school.teachers.show';

        return Redirect::route($routeName, $penalizable->id)->with('success', 'تم تسجيل العقوبة بنجاح.');
    }
}

