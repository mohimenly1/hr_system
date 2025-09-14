<?php

namespace App\Http\Controllers\HR;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Models\EvaluationCriterion;
use App\Models\DeductionLog; // <-- إضافة مهمة
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; // <-- إضافة مهمة
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Controller;

class EmployeePerformanceEvaluationController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Employee $employee)
    {
        $this->authorize('create', [PerformanceEvaluation::class, $employee]);
        
        $user = Auth::user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'evaluation_date' => 'required|date',
            'overall_notes' => 'nullable|string',
            'results' => 'required|array',
            'results.*.criterion_id' => 'required|exists:evaluation_criteria,id',
            'results.*.score' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // --- 1. تحديد تاريخ التقييم والبحث عن العقوبات في نفس الشهر ---
            $evaluationDate = Carbon::parse($validated['evaluation_date']);

            $penaltiesWithCriteria = $employee->penalties()
                ->whereYear('issued_at', $evaluationDate->year)
                ->whereMonth('issued_at', $evaluationDate->month)
                ->with('penaltyType.criteria')
                ->get();

            $deductionsByCriterion = [];
            foreach ($penaltiesWithCriteria as $penalty) {
                foreach ($penalty->penaltyType->criteria as $criterion) {
                    if (!isset($deductionsByCriterion[$criterion->id])) {
                        $deductionsByCriterion[$criterion->id] = [];
                    }
                    $deductionsByCriterion[$criterion->id][] = [
                        'penalty_id' => $penalty->id,
                        'points' => $criterion->pivot->deduction_points,
                    ];
                }
            }
            
            $evaluation = $employee->evaluations()->create([
                'title' => $validated['title'],
                'evaluation_date' => $validated['evaluation_date'],
                'overall_notes' => $validated['overall_notes'],
            ]);

            $totalMaxScore = 0;
            $finalTotalScore = 0; // استخدام متغير جديد للنتيجة النهائية

            foreach ($validated['results'] as $resultData) {
                $criterion = EvaluationCriterion::find($resultData['criterion_id']);
                $userScore = min($resultData['score'], $criterion->max_score);
                
                $finalScore = $userScore;

                // --- 2. تطبيق الخصم وتسجيل التفاصيل ---
                if (isset($deductionsByCriterion[$criterion->id])) {
                    foreach ($deductionsByCriterion[$criterion->id] as $deduction) {
                        $pointsToDeduct = $deduction['points'];
                        $finalScore -= $pointsToDeduct;

                        // تسجيل تفاصيل عملية الخصم
                        DeductionLog::create([
                            'performance_evaluation_id' => $evaluation->id,
                            'penalty_id' => $deduction['penalty_id'],
                            'evaluation_criterion_id' => $criterion->id,
                            'logged_by_user_id' => $user->id,
                            'points_deducted' => $pointsToDeduct,
                        ]);
                    }
                }

                $finalScore = max(0, $finalScore); // منع الدرجات السالبة
                $scoreField = $user->hasAnyRole(['admin', 'hr-manager']) ? 'admin_score' : 'manager_score';
                
                $evaluation->results()->create([
                    'evaluation_criterion_id' => $criterion->id,
                    $scoreField => $finalScore, // <-- حفظ الدرجة النهائية بعد الخصم
                ]);
                
                $totalMaxScore += $criterion->max_score;
                $finalTotalScore += $finalScore; // <-- جمع الدرجات النهائية
            }
            
            $finalPercentage = ($totalMaxScore > 0) ? ($finalTotalScore / $totalMaxScore) * 100 : 0;
            $evaluation->final_score_percentage = round($finalPercentage, 2);
            $evaluation->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Employee Evaluation creation failed: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ غير متوقع أثناء حفظ التقييم.');
        }

        return Redirect::route('hr.employees.show', $employee)->with('success', 'تم حفظ التقييم وتطبيق الخصومات بنجاح.');
    }
}