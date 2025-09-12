<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EvaluationCriterion;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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
            $evaluation = $employee->evaluations()->create([
                'title' => $validated['title'],
                'evaluation_date' => $validated['evaluation_date'],
                'overall_notes' => $validated['overall_notes'],
            ]);

            $totalMaxScore = 0;
            $totalUserScore = 0;

            foreach ($validated['results'] as $resultData) {
                $criterion = EvaluationCriterion::find($resultData['criterion_id']);
                if ($resultData['score'] > $criterion->max_score) {
                    $resultData['score'] = $criterion->max_score;
                }

                $scoreField = $user->hasAnyRole(['admin', 'hr-manager']) ? 'admin_score' : 'manager_score';
                
                $evaluation->results()->create([
                    'evaluation_criterion_id' => $criterion->id,
                    $scoreField => $resultData['score'],
                ]);
                
                $totalMaxScore += $criterion->max_score;
                $totalUserScore += $resultData['score'];
            }
            
            $finalPercentage = ($totalMaxScore > 0) ? ($totalUserScore / $totalMaxScore) * 100 : 0;
            $evaluation->final_score_percentage = round($finalPercentage, 2);
            $evaluation->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ أثناء حفظ التقييم: ' . $e->getMessage());
        }

        return Redirect::route('hr.employees.show', $employee)->with('success', 'تم حفظ التقييم بنجاح.');
    }
}
