<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithCustomStartCell, WithEvents
{
    protected $allDaysData;
    protected $employee;
    protected $month;
    protected $statistics;
    protected $employeeData; // بيانات الموظف بشكل مباشر

    public function __construct($allDaysData, $employee, $month, $statistics)
    {
        $this->allDaysData = $allDaysData;
        $this->employee = $employee;
        $this->month = $month;
        $this->statistics = $statistics;

        // تحضير بيانات الموظف بشكل مباشر
        $this->employeeData = [
            'name' => null,
            'full_name' => null,
            'employee_id' => null,
            'department_name' => null,
            'job_title' => null,
        ];

        // تحميل العلاقات إذا لم تكن محملة
        if ($employee) {
            if (!$employee->relationLoaded('user')) {
                $employee->load('user');
            }
            if (!$employee->relationLoaded('department')) {
                $employee->load('department');
            }

            if ($employee->user) {
                $this->employeeData['name'] = $employee->user->name ?? 'غير محدد';
                $this->employeeData['full_name'] = $employee->user->full_name ?? $employee->user->name ?? 'غير محدد';
            }

            $this->employeeData['employee_id'] = $employee->employee_id ?? 'غير محدد';
            $this->employeeData['department_name'] = $employee->department ? ($employee->department->name ?? 'غير محدد') : 'غير محدد';
            $this->employeeData['job_title'] = $employee->job_title ?? 'غير محدد';
        }
    }

    public function collection()
    {
        return collect($this->allDaysData);
    }

    public function startCell(): string
    {
        return 'A8'; // البيانات تبدأ من السطر 8 بعد Header
    }

    public function headings(): array
    {
        return [
            'التاريخ',
            'اليوم',
            'وقت الدخول',
            'وقت الخروج',
            'الحالة',
            'ملاحظات',
        ];
    }

    public function map($day): array
    {
        $date = \Carbon\Carbon::parse($day['date']);
        $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);

        $dayName = $date->locale('ar')->translatedFormat('l');
        if ($isWeekend) {
            if ($date->dayOfWeek === \Carbon\Carbon::FRIDAY) {
                $dayName = 'الجمعة (عطلة نهاية الأسبوع)';
            } else {
                $dayName = 'السبت (عطلة نهاية الأسبوع)';
            }
        }

        $statusMap = [
            'present' => 'حاضر',
            'absent' => 'غائب',
            'late' => 'متأخر',
            'on_leave' => 'إجازة',
            'holiday' => 'عطلة',
        ];

        return [
            $date->locale('ar')->translatedFormat('d/m/Y'),
            $dayName,
            $day['check_in'] ?? '---',
            $day['check_out'] ?? '---',
            isset($day['status']) ? ($statusMap[$day['status']] ?? $day['status']) : ($isWeekend ? 'عطلة نهاية الأسبوع' : 'غياب'),
            $day['notes'] ?? 'لا يوجد',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // استخدام البيانات المحضرة مسبقاً
                $employeeName = $this->employeeData['full_name'] ?? $this->employeeData['name'] ?? 'غير محدد';
                $employeeId = $this->employeeData['employee_id'] ?? 'غير محدد';
                $departmentName = $this->employeeData['department_name'] ?? 'غير محدد';
                $jobTitle = $this->employeeData['job_title'] ?? 'غير محدد';
                $monthName = $this->month ?? 'غير محدد';

                // Header Section - Employee Info
                // السطر الأول: اسم الموظف ورقم الموظف
                $sheet->setCellValue('A1', 'اسم الموظف:');
                $sheet->setCellValue('B1', $employeeName);
                $sheet->setCellValue('D1', 'رقم الموظف:');
                $sheet->setCellValue('E1', $employeeId);

                // السطر الثاني: القسم والمسمى الوظيفي
                $sheet->setCellValue('A2', 'القسم:');
                $sheet->setCellValue('B2', $departmentName);
                $sheet->setCellValue('D2', 'المسمى الوظيفي:');
                $sheet->setCellValue('E2', $jobTitle);

                // السطر الثالث: الشهر
                $sheet->setCellValue('A3', 'الشهر:');
                $sheet->setCellValue('B3', $monthName);

                // Statistics Section
                $sheet->setCellValue('A5', 'الإحصائيات');
                $sheet->setCellValue('A6', 'عدد أيام الحضور:');
                $sheet->setCellValue('B6', $this->statistics['actual_present_days'] ?? 0);
                $sheet->setCellValue('D6', 'عدد أيام الغياب:');
                $sheet->setCellValue('E6', $this->statistics['actual_absent_days'] ?? 0);

                // Merge cells for header sections - دمج الخلايا بشكل صحيح
                $sheet->mergeCells('B1:C1'); // دمج B و C لاسم الموظف
                $sheet->mergeCells('E1:F1'); // دمج E و F لرقم الموظف
                $sheet->mergeCells('B2:C2'); // دمج B و C للقسم
                $sheet->mergeCells('E2:F2'); // دمج E و F للمسمى الوظيفي
                $sheet->mergeCells('B3:F3'); // دمج B إلى F للشهر
                $sheet->mergeCells('A5:F5'); // دمج A إلى F لعنوان الإحصائيات
                $sheet->mergeCells('B6:C6'); // دمج B و C لعدد أيام الحضور
                $sheet->mergeCells('E6:F6'); // دمج E و F لعدد أيام الغياب

                // Style Header Section
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ];

                $infoStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E7E6E6'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ];

                // Apply styles
                $sheet->getStyle('A1:F3')->applyFromArray($infoStyle);
                $sheet->getStyle('A5:F5')->applyFromArray($headerStyle);
                $sheet->getStyle('A6:F6')->applyFromArray($infoStyle);

                // Style column headers (row 8)
                $columnHeaderStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ];

                $sheet->getStyle('A8:F8')->applyFromArray($columnHeaderStyle);

                // Auto-size columns
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(25);
                $sheet->getRowDimension(5)->setRowHeight(30);
                $sheet->getRowDimension(8)->setRowHeight(30);

                // Style data rows
                $lastRow = $sheet->getHighestRow();
                if ($lastRow > 8) {
                    $dataStyle = [
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC'],
                            ],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ];

                    $sheet->getStyle('A9:F' . $lastRow)->applyFromArray($dataStyle);

                    // Highlight weekend rows
                    for ($row = 9; $row <= $lastRow; $row++) {
                        $dayValue = $sheet->getCell('B' . $row)->getValue();
                        if (strpos($dayValue, 'عطلة نهاية الأسبوع') !== false) {
                            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'E8F4F8'],
                                ],
                            ]);
                        }
                    }
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function title(): string
    {
        return 'سجلات الحضور';
    }
}
