<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Section;
use App\Models\SchedulingConstraint;
use App\Models\ScheduleTemplate;
use App\Models\TimetableEntry;
use App\Services\TimetableGeneratorService;
use App\Models\Shift; // <-- الخطوة الأولى: استدعاء نموذج الدوامات

class SchedulingController extends Controller
{
    public function index(Request $request)
    {
        $personnelConstraints = null;
        $activePersonIdentifier = null;

        if ($request->has('person_id') && $request->has('person_type')) {
            $personId = $request->person_id;
            $personType = $request->person_type;
            
            $constraints = SchedulingConstraint::where('schedulable_id', $personId)
                ->where('schedulable_type', $personType)
                ->get();
            
            $personnelConstraints = $constraints->pluck('value', 'constraint_type')->toArray();

            $modelName = last(explode('\\', $personType));
            $activePersonIdentifier = "{$modelName}-{$personId}";
        }

        return Inertia::render('Integrations/Scheduling/Index', [
            'employees' => Employee::with('user')->get(['id', 'user_id']),
            'teachers' => Teacher::with('user')->get(['id', 'user_id']),
            'subjects' => Subject::all(['id', 'name']),
            'sections' => Section::with('grade:id,name')->get(['id', 'name', 'grade_id']),
            'templates' => ScheduleTemplate::with('constraints')->where('is_active', true)->get(),
            'personnelConstraints' => $personnelConstraints,
            'timetables' => TimetableEntry::with('schedulable.user', 'subject', 'section.grade')->get(),
            'activePersonIdentifier' => $activePersonIdentifier,
            'shifts' => Shift::all(), // <-- الخطوة الثانية: جلب جميع الدوامات وتمريرها للواجهة
        ]);
    }

    public function storeConstraints(Request $request)
    {
        $data = $request->except(['schedulable_id', 'schedulable_type']);
        $schedulableId = $request->input('schedulable_id');
        $schedulableType = $request->input('schedulable_type');

        SchedulingConstraint::where('schedulable_id', $schedulableId)
            ->where('schedulable_type', $schedulableType)
            ->delete();
        
        foreach ($data as $key => $value) {
            // Ensure value is not null before creating constraint
            if ($value !== null) {
                SchedulingConstraint::create([
                    'schedulable_id' => $schedulableId,
                    'schedulable_type' => $schedulableType,
                    'constraint_type' => $key,
                    'value' => $value,
                ]);
            }
        }

        return redirect()->back()->with('success', 'تم حفظ القيود بنجاح.');
    }

    // --- Template Management Methods ---

    public function storeTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:schedule_templates,name',
            'type' => 'required|in:teacher,employee,general',
            'is_active' => 'required|boolean',
        ]);
        ScheduleTemplate::create($request->all());
        return redirect()->back()->with('success', 'تم إنشاء القالب بنجاح.');
    }

    public function updateTemplate(Request $request, ScheduleTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|unique:schedule_templates,name,' . $template->id,
            'type' => 'required|in:teacher,employee,general',
            'is_active' => 'required|boolean',
        ]);
        $template->update($request->all());
        return redirect()->back()->with('success', 'تم تحديث القالب بنجاح.');
    }

    public function storeTemplateConstraints(Request $request, ScheduleTemplate $template)
    {
        $template->constraints()->delete();
        $constraintsData = $request->all();
        foreach ($constraintsData as $key => $value) {
            $template->constraints()->create([
                'constraint_type' => $key,
                'value' => $value,
            ]);
        }
        return redirect()->route('hr.integrations.scheduling.index')->with('success', 'تم حفظ قيود القالب بنجاح.');
    }
    
    public function generateTimetable(Request $request, TimetableGeneratorService $generator)
    {
        try {
            $result = $generator->generate();
            
            $redirectParams = [];
            if ($request->has('person_identifier') && $request->person_identifier) {
                 list($type, $id) = explode('-', $request->person_identifier);
                 $redirectParams['person_type'] = "App\\Models\\{$type}";
                 $redirectParams['person_id'] = $id;
            }

            return redirect()->route('hr.integrations.scheduling.index', $redirectParams)
                             ->with('success', $result['message']);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'حدث خطأ أثناء عملية الجدولة: ' . $e->getMessage());
        }
    }
}

