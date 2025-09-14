<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EvaluationCriterion;
use App\Models\PenaltyType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log; // <-- إضافة مهمة

class PenaltySettingsController extends Controller
{
    use AuthorizesRequests;

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

        Log::debug("================ PENALTY UPDATE START ================");
        Log::debug("PENALTY_UPDATE_DEBUG: Attempting to update PenaltyType ID: {$penalty_type->id}");
        Log::debug("PENALTY_UPDATE_DEBUG: Incoming request data:", $request->all());

        $validated = $request->validate($this->validationRules($penalty_type->id));
        
        Log::debug("PENALTY_UPDATE_DEBUG: Data passed validation:", $validated);

        $penalty_type->update($validated);
        
        Log::debug("PENALTY_UPDATE_DEBUG: Main penalty type model updated in DB.");

        if ($validated['affects_evaluation'] && isset($validated['criteria_deductions'])) {
            Log::debug("PENALTY_UPDATE_DEBUG: 'affects_evaluation' is true. Syncing criteria...");
            $this->syncCriteria($penalty_type, $validated['criteria_deductions']);
            Log::debug("PENALTY_UPDATE_DEBUG: Criteria sync process completed.");
        } else {
            Log::debug("PENALTY_UPDATE_DEBUG: 'affects_evaluation' is false. Detaching all criteria.");
            $penalty_type->criteria()->detach();
        }

        Log::debug("================ PENALTY UPDATE END ================");
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
        $syncData = collect($deductions)
            // --- هذا هو السطر الجديد الذي يحل المشكلة ---
            ->filter(fn($item) => isset($item['points']) && $item['points'] > 0)
            ->mapWithKeys(function ($item) {
                return [$item['id'] => ['deduction_points' => $item['points']]];
            });
    
        Log::debug("PENALTY_UPDATE_DEBUG: Data prepared for sync after filtering:", $syncData->toArray());
        $penaltyType->criteria()->sync($syncData);
    }
}

