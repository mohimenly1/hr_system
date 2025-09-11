<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EvaluationCriterion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EvaluationSettingsController extends Controller
{
    use AuthorizesRequests; // <-- إضافة السمة اللازمة

    public function index()
    {
        // التحقق من صلاحية العرض
        $this->authorize('viewAny', EvaluationCriterion::class);

        return Inertia::render('HR/EvaluationSettings/Index', [
            'criteria' => EvaluationCriterion::all(),
        ]);
    }

    public function store(Request $request)
    {
        // التحقق من صلاحية الإنشاء
        $this->authorize('create', EvaluationCriterion::class);

        $validated = $request->validate([
            'name' => 'required|string|unique:evaluation_criteria,name',
            'description' => 'nullable|string',
            'max_score' => 'required|integer|min:1|max:100',
            'affects_salary' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        EvaluationCriterion::create($validated);
        return Redirect::back()->with('success', 'تم إنشاء معيار التقييم بنجاح.');
    }

    public function update(Request $request, EvaluationCriterion $criterion)
    {
        // التحقق من صلاحية التحديث
        $this->authorize('update', $criterion);

        $validated = $request->validate([
            'name' => 'required|string|unique:evaluation_criteria,name,' . $criterion->id,
            'description' => 'nullable|string',
            'max_score' => 'required|integer|min:1|max:100',
            'affects_salary' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $criterion->update($validated);
        return Redirect::back()->with('success', 'تم تحديث معيار التقييم بنجاح.');
    }
}

