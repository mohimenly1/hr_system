<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\DeductionRule;
use App\Models\PenaltyType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DeductionRulesController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', DeductionRule::class);

        return Inertia::render('HR/DeductionRules/Index', [
            'deductionRules' => DeductionRule::with('penaltyType')
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->get(),
            'penaltyTypes' => PenaltyType::where('is_active', true)
                ->where('affects_salary', true)
                ->get(['id', 'name', 'deduction_type', 'deduction_amount']), // إضافة deduction_type و deduction_amount لعرض القيم الافتراضية
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', DeductionRule::class);

        return Inertia::render('HR/DeductionRules/Create', [
            'penaltyTypes' => PenaltyType::where('is_active', true)
                ->where('affects_salary', true)
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', DeductionRule::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'penalty_type_id' => 'required|exists:penalty_types,id',
            'deduction_type' => 'nullable|in:fixed,percentage,daily_salary,hourly_salary',
            'deduction_amount' => 'nullable|numeric|min:0',
            'deduction_days' => 'nullable|integer|min:1|required_if:deduction_type,daily_salary',
            'deduction_hours' => 'nullable|numeric|min:0|required_if:deduction_type,hourly_salary',
            'min_deduction' => 'nullable|numeric|min:0',
            'max_deduction' => 'nullable|numeric|min:0|gte:min_deduction',
            'conditions' => 'nullable|array',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        DeductionRule::create($validated);

        return Redirect::route('hr.deduction-rules.index')
            ->with('success', 'تم إنشاء معادلة الخصم بنجاح.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DeductionRule $deductionRule)
    {
        $this->authorize('view', $deductionRule);

        return Inertia::render('HR/DeductionRules/Show', [
            'deductionRule' => $deductionRule->load('penaltyType'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeductionRule $deductionRule)
    {
        $this->authorize('update', $deductionRule);

        return Inertia::render('HR/DeductionRules/Edit', [
            'deductionRule' => $deductionRule->load('penaltyType'),
            'penaltyTypes' => PenaltyType::where('is_active', true)
                ->where('affects_salary', true)
                ->get(['id', 'name', 'deduction_type', 'deduction_amount']), // إضافة deduction_type و deduction_amount
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeductionRule $deductionRule)
    {
        $this->authorize('update', $deductionRule);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'penalty_type_id' => 'required|exists:penalty_types,id',
            'deduction_type' => 'nullable|in:fixed,percentage,daily_salary,hourly_salary', // اختياري - سيستخدم القيمة من PenaltyType إذا لم يتم تحديدها
            'deduction_amount' => 'nullable|numeric|min:0', // اختياري - سيستخدم القيمة من PenaltyType إذا لم يتم تحديدها
            'deduction_days' => 'nullable|integer|min:1|required_if:deduction_type,daily_salary',
            'deduction_hours' => 'nullable|numeric|min:0|required_if:deduction_type,hourly_salary',
            'min_deduction' => 'nullable|numeric|min:0',
            'max_deduction' => 'nullable|numeric|min:0|gte:min_deduction',
            'conditions' => 'nullable|array',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        $deductionRule->update($validated);

        return Redirect::route('hr.deduction-rules.index')
            ->with('success', 'تم تحديث معادلة الخصم بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeductionRule $deductionRule)
    {
        $this->authorize('delete', $deductionRule);

        $deductionRule->delete();

        return Redirect::route('hr.deduction-rules.index')
            ->with('success', 'تم حذف معادلة الخصم بنجاح.');
    }
}
