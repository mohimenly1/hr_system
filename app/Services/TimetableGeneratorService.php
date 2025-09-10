<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ScheduleTemplate;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class TimetableGeneratorService
{
    protected Collection $personnel;
    protected Collection $scheduleTemplates;
    protected Collection $shifts;

    public function generate()
    {
        Log::info('Starting timetable generation process...');
        TimetableEntry::query()->truncate();
        Log::info('Cleared old timetable entries.');

        $this->loadData();

        foreach ($this->personnel as $person) {
            $this->schedulePerson($person);
        }

        Log::info('Timetable generation process finished.');
        return ['message' => 'تم إكمال عملية الجدولة بنجاح.'];
    }

    private function loadData(): void
    {
        $employees = Employee::with(['user', 'constraints'])->get();
        $teachers = Teacher::with(['user', 'constraints'])->get();
        $this->personnel = $employees->concat($teachers);
        $this->scheduleTemplates = ScheduleTemplate::with('constraints')->where('is_active', true)->get();
        $this->shifts = Shift::all(); // تحميل جميع الدوامات مرة واحدة
        Log::info("Loaded {$this->personnel->count()} personnel and {$this->shifts->count()} shifts for scheduling.");
    }

    private function getConsolidatedConstraints(Employee|Teacher $person): array
    {
        $individualConstraints = $person->constraints->pluck('value', 'constraint_type');
        $templateConstraints = collect();
        return $individualConstraints->union($templateConstraints)->all();
    }

    private function schedulePerson(Employee|Teacher $person): void
    {
        $constraints = $this->getConsolidatedConstraints($person);
        Log::info("Processing {$person->user->name} with constraints: ", $constraints);

        if ($person instanceof Employee && !empty($constraints['required_days'])) {
            $startTime = '08:00:00'; // Default start time
            $endTime = '16:00:00';   // Default end time

            // الأولوية الأولى: التحقق من وجود دوام محدد
            if (!empty($constraints['assigned_shift_id'])) {
                $assignedShift = $this->shifts->firstWhere('id', $constraints['assigned_shift_id']);
                if ($assignedShift) {
                    $startTime = $assignedShift->start_time;
                    $endTime = $assignedShift->end_time;
                }
            }
            // الأولوية الثانية: الاعتماد على إجمالي الساعات الأسبوعية
            elseif (!empty($constraints['total_hours_per_week'])) {
                $totalHours = (int)$constraints['total_hours_per_week'];
                $numDays = count($constraints['required_days']);
                if ($numDays > 0) {
                    $hoursPerDay = floor($totalHours / $numDays);
                    $endCarbon = Carbon::parse($startTime)->addHours($hoursPerDay);
                    $endTime = $endCarbon->format('H:i:s');
                }
            }

            foreach ($constraints['required_days'] as $day) {
                TimetableEntry::create([
                    'schedulable_id' => $person->id,
                    'schedulable_type' => get_class($person),
                    'day_of_week' => $day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);
            }
        } 
        elseif ($person instanceof Teacher && !empty($constraints['required_days']) && !empty($constraints['allowed_subjects'])) {
            $classDuration = 60;
            $dailyStartTime = Carbon::parse('08:00:00');
            
            $possibleClasses = [];
            foreach($constraints['allowed_subjects'] as $subjectId) {
                foreach($constraints['allowed_sections'] as $sectionId) {
                    $possibleClasses[] = ['subject_id' => $subjectId, 'section_id' => $sectionId];
                }
            }

            if (empty($possibleClasses)) return;

            $classIndex = 0;
            foreach ($constraints['required_days'] as $day) {
                $currentTime = $dailyStartTime->copy();
                for ($i = 0; $i < ($constraints['max_subjects_per_day'] ?? 2); $i++) {
                    if ($classIndex >= count($possibleClasses)) break;
                    
                    $class = $possibleClasses[$classIndex];
                    
                    TimetableEntry::create([
                        'schedulable_id' => $person->id,
                        'schedulable_type' => get_class($person),
                        'day_of_week' => $day,
                        'start_time' => $currentTime->format('H:i:s'),
                        'end_time' => $currentTime->copy()->addMinutes($classDuration)->format('H:i:s'),
                        'subject_id' => $class['subject_id'],
                        'section_id' => $class['section_id'],
                    ]);
                    
                    $currentTime->addMinutes($classDuration);
                    $classIndex++;
                }
            }
        }
    }
}

