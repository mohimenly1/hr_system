<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payslip;
use App\Models\PayslipItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

class PayrollController extends Controller
{
    /**
     * Display a listing of the payslips.
     */
    public function index()
    {
        $payslips = Payslip::with('employee.user')->latest()->paginate(15);
        return Inertia::render('HR/Payroll/Index', [
            'payslips' => $payslips
        ]);
    }

    /**
     * Show the form for creating a new payroll run.
     */
    public function create()
    {
        return Inertia::render('HR/Payroll/Generate');
    }

    public function show(Payslip $payslip)
    {
        // Eager load all related data for the view
        $payslip->load(['employee.user', 'employee.department', 'contract', 'items']);

        return Inertia::render('HR/Payroll/Show', [
            'payslip' => $payslip
        ]);
    }
    /**
     * Store a newly created payroll run in storage.
     */
    public function store(Request $request)
    {
        // 1. التحقق من صحة المدخلات (الشهر والسنة)
        // إذا فشل هذا التحقق، سيقوم Laravel بإعادة توجيهك تلقائياً مع إرسال أخطاء التحقق
        // والتي يجب أن تظهر الآن في صفحة Generate.vue بفضل التحديث الأخير.
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
        ]);

        $month = $request->input('month');
        $year = $request->input('year');
        $generationDate = Carbon::create($year, $month);

        // 2. البحث عن الموظفين الذين لديهم عقود سارية
        $activeContracts = Contract::where('status', 'active')
            ->where('start_date', '<=', $generationDate->endOfMonth())
            ->where(function ($query) use ($generationDate) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $generationDate->startOfMonth());
            })
            ->get();
        
        // 3. هذا هو السبب الأكثر احتمالاً للمشكلة!
        // إذا لم يتم العثور على أي عقود سارية، سيتم إعادة توجيهك إلى صفحة الإنشاء
        // مع رسالة خطأ من نوع "flash message".
        if ($activeContracts->isEmpty()) {
            return Redirect::route('hr.payroll.create')->with('error', 'لا يوجد موظفين بعقود سارية لهذا الشهر.');
        }

        $generatedCount = 0;
        foreach ($activeContracts as $contract) {
            $existingPayslip = Payslip::where('employee_id', $contract->employee_id)
                ->where('month', $month)
                ->where('year', $year)
                ->exists();

            if ($existingPayslip) {
                continue;
            }

            $grossSalary = $contract->total_salary;
            $totalDeductions = 0; 
            $netSalary = $grossSalary - $totalDeductions;
            
            $payslip = Payslip::create([
                'employee_id' => $contract->employee_id,
                'contract_id' => $contract->id,
                'month' => $month,
                'year' => $year,
                'issue_date' => now(),
                'gross_salary' => $grossSalary,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'status' => 'pending',
            ]);

            $payslip->items()->create(['type' => 'earning', 'description' => 'الراتب الأساسي', 'amount' => $contract->basic_salary]);
            if ($contract->housing_allowance > 0) {
                $payslip->items()->create(['type' => 'earning', 'description' => 'بدل سكن', 'amount' => $contract->housing_allowance]);
            }
            if ($contract->transportation_allowance > 0) {
                $payslip->items()->create(['type' => 'earning', 'description' => 'بدل مواصلات', 'amount' => $contract->transportation_allowance]);
            }
            if ($contract->other_allowances > 0) {
                 $payslip->items()->create(['type' => 'earning', 'description' => 'بدلات أخرى', 'amount' => $contract->other_allowances]);
            }
            
            $generatedCount++;
        }

        if ($generatedCount > 0) {
            return Redirect::route('hr.payroll.index')->with('success', "تم إنشاء {$generatedCount} قسيمة راتب بنجاح.");
        } else {
            return Redirect::route('hr.payroll.create')->with('info', 'تم إنشاء الرواتب لهذا الشهر مسبقاً.');
        }
    }
}
