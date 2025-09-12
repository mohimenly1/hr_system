<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EvaluationCriterion;
use App\Models\PenaltyType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PenaltySettingsController extends Controller
{
    use AuthorizesRequests; // <-- إضافة السمة اللازمة

    public function index()
    {
        $this->authorize('viewAny', PenaltyType::class);

        return Inertia::render('HR/PenaltySettings/Index', [
            'penaltyTypes' => PenaltyType::with('criteria')->get(),
            'evaluationCriteria' => EvaluationCriterion::where('is_active', true)->get(['id', 'name', 'max_score']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PenaltyType::class);

        $validated = $request->validate($this->validationRules());

        $penaltyType = PenaltyType::create($validated);
        if ($validated['affects_evaluation'] && isset($validated['criteria_deductions'])) {
            $this->syncCriteria($penaltyType, $validated['criteria_deductions']);
        }

        return Redirect::back()->with('success', 'تم إنشاء نوع العقوبة بنجاح.');
    }

    public function update(Request $request, PenaltyType $penalty_type)
    {
        $this->authorize('update', $penalty_type);

        $validated = $request->validate($this->validationRules($penalty_type->id));

        $penalty_type->update($validated);
        if ($validated['affects_evaluation'] && isset($validated['criteria_deductions'])) {
            $this->syncCriteria($penalty_type, $validated['criteria_deductions']);
        } else {
            $penalty_type->criteria()->detach();
        }

        return Redirect::back()->with('success', 'تم تحديث نوع العقوبة بنجاح.');
    }

    private function validationRules($id = null): array
    {
        return [
            'name' => 'required|string|unique:penalty_types,name,' . $id,
            'description' => 'nullable|string',
            'affects_evaluation' => 'required|boolean',
            'affects_salary' => 'required|boolean',
            'deduction_type' => 'nullable|required_if:affects_salary,true|in:fixed,percentage',
            'deduction_amount' => 'nullable|required_if:affects_salary,true|numeric|min:0',
            'is_active' => 'required|boolean',
            'criteria_deductions' => 'nullable|required_if:affects_evaluation,true|array',
            'criteria_deductions.*.id' => 'required|exists:evaluation_criteria,id',
            'criteria_deductions.*.points' => 'required|integer|min:0',
        ];
    }

    private function syncCriteria(PenaltyType $penaltyType, array $deductions): void
    {
        $syncData = collect($deductions)->mapWithKeys(function ($item) {
            return [$item['id'] => ['deduction_points' => $item['points']]];
        });
        $penaltyType->criteria()->sync($syncData);
    }
}

