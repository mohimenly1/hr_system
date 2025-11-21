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

class GeneralAttendanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithCustomStartCell, WithEvents
{
    protected $items;
    protected $type;
    protected $month;
    protected $allDays;
    protected $dayHeaders;

    public function __construct($items, $type, $month)
    {
        $this->items = $items;
        $this->type = $type; // 'employees' or 'teachers'
        $this->month = $month;

        // Get all days from first item (all items have same days)
        if (count($items) > 0 && isset($items[0]['all_days'])) {
            $this->allDays = $items[0]['all_days'];
            $this->dayHeaders = $this->generateDayHeaders();
        } else {
            $this->allDays = [];
            $this->dayHeaders = [];
        }
    }

    private function generateDayHeaders()
    {
        $headers = [];
        foreach ($this->allDays as $dateStr) {
            $date = \Carbon\Carbon::parse($dateStr);
            $dayNum = $date->day;
            $dayName = $date->locale('ar')->translatedFormat('D');
            $headers[] = $dayNum . "\n" . $dayName; // Day number and day name
        }
        return $headers;
    }

    public function collection()
    {
        return collect($this->items);
    }

    public function startCell(): string
    {
        return 'A6'; // البيانات تبدأ من السطر 6 بعد Header
    }

    public function headings(): array
    {
        $baseHeaders = [];

        if ($this->type === 'employees') {
            $baseHeaders = [
                'رقم الموظف',
                'اسم الموظف',
                'القسم',
                'المسمى الوظيفي',
            ];
        } else {
            $baseHeaders = [
                'اسم المعلم',
                'القسم',
                'التخصص',
            ];
        }

        // Add day headers
        $dayHeaders = $this->dayHeaders;

        // Add summary columns at the end
        $summaryHeaders = [
            'أيام الحضور',
            'أيام الغياب',
            'أيام التأخر',
            'أيام الإجازة',
            'نسبة الحضور %',
        ];

        return array_merge($baseHeaders, $dayHeaders, $summaryHeaders);
    }

    public function map($item): array
    {
        $stats = $item['statistics'];
        $dailyData = $item['daily_data'] ?? [];

        // Base data
        if ($this->type === 'employees') {
            $row = [
                $item['employee_id'] ?? 'غير محدد',
                $item['name'],
                $item['department'],
                $item['job_title'],
            ];
        } else {
            $row = [
                $item['name'],
                $item['department'],
                $item['specialization'],
            ];
        }

        // Daily attendance data
        foreach ($this->allDays as $dateStr) {
            $status = $dailyData[$dateStr] ?? 'absent';

            // Map status to display text
            $statusMap = [
                'present' => 'ح',
                'absent' => 'غ',
                'late' => 'ت',
                'on_leave' => 'إ',
                'holiday' => 'ع',
                'weekend' => 'ع',
            ];

            $row[] = $statusMap[$status] ?? 'غ';
        }

        // Summary columns
        $row[] = $stats['actual_present_days'];
        $row[] = $stats['actual_absent_days'];
        $row[] = $stats['late_days'];
        $row[] = $stats['leave_days'];
        $row[] = $stats['attendance_rate'] . '%';

        return $row;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $typeLabel = $this->type === 'employees' ? 'الموظفين' : 'المعلمين';
                $totalCount = count($this->items);

                $baseColumns = $this->type === 'employees' ? 4 : 3;
                $dayColumns = count($this->allDays);
                $summaryColumns = 5;
                $lastColumn = $this->getColumnLetter($baseColumns + $dayColumns + $summaryColumns);

                // Calculate totals
                $totalPresent = collect($this->items)->sum(fn($item) => $item['statistics']['actual_present_days']);
                $totalAbsent = collect($this->items)->sum(fn($item) => $item['statistics']['actual_absent_days']);
                $totalLate = collect($this->items)->sum(fn($item) => $item['statistics']['late_days']);
                $totalLeave = collect($this->items)->sum(fn($item) => $item['statistics']['leave_days']);
                $avgRate = $totalCount > 0 ? round(collect($this->items)->avg(fn($item) => $item['statistics']['attendance_rate']), 2) : 0;

                // Header Section
                $sheet->setCellValue('A1', 'تقرير الحضور والغياب العام المفصل');
                $sheet->setCellValue('A2', 'نوع التقرير:');
                $sheet->setCellValue('B2', $typeLabel);
                $sheet->setCellValue('D2', 'الشهر:');
                $sheet->setCellValue('E2', $this->month);
                $sheet->setCellValue('A3', 'إجمالي العدد:');
                $sheet->setCellValue('B3', $totalCount);
                $sheet->setCellValue('A4', 'مفتاح الرموز: ح = حاضر، غ = غائب، ت = متأخر، إ = إجازة، ع = عطلة');

                // Merge header cells
                $sheet->mergeCells('A1:' . $lastColumn . '1');
                $sheet->mergeCells('B2:C2');
                $sheet->mergeCells('E2:' . $lastColumn . '2');
                $sheet->mergeCells('B3:' . $lastColumn . '3');
                $sheet->mergeCells('A4:' . $lastColumn . '4');

                // Style Header
                $mainHeaderStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'], // Dark blue
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

                $subHeaderStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2'], // Light blue
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

                $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($mainHeaderStyle);
                $sheet->getStyle('A2:' . $lastColumn . '3')->applyFromArray($subHeaderStyle);
                $sheet->getStyle('A4:' . $lastColumn . '4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(35);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(25);
                $sheet->getRowDimension(4)->setRowHeight(20);
                $sheet->getRowDimension(6)->setRowHeight(40);

                // Style column headers (row 6)
                $columnHeaderStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'], // Blue
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ];

                $sheet->getStyle('A6:' . $lastColumn . '6')->applyFromArray($columnHeaderStyle);

                // Style data rows and color code daily attendance
                $lastRow = $sheet->getHighestRow();
                if ($lastRow > 6) {
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

                    $sheet->getStyle('A7:' . $lastColumn . $lastRow)->applyFromArray($dataStyle);

                    // Color code daily attendance cells
                    foreach ($this->items as $itemIndex => $item) {
                        $row = 7 + $itemIndex; // Data starts at row 7
                        $dailyData = $item['daily_data'] ?? [];

                        $dayColIndex = $baseColumns + 1; // Start after base columns (1-indexed for Excel)

                        foreach ($this->allDays as $dateStr) {
                            $status = $dailyData[$dateStr] ?? 'absent';
                            $colLetter = $this->getColumnLetter($dayColIndex);

                            $colorMap = [
                                'present' => 'C6EFCE', // Light green
                                'absent' => 'FFC7CE', // Light red
                                'late' => 'FFEB9C', // Light yellow
                                'on_leave' => 'BDD7EE', // Light blue
                                'holiday' => 'E7E6E6', // Light gray
                                'weekend' => 'D9E1F2', // Light blue-gray
                            ];

                            $color = $colorMap[$status] ?? 'FFFFFF';

                            $sheet->getStyle($colLetter . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => $color],
                                ],
                                'font' => [
                                    'bold' => true,
                                    'size' => 10,
                                ],
                            ]);

                            $dayColIndex++;
                        }
                    }

                    // Highlight rows with low attendance rate
                    $attendanceRateCol = $this->getColumnLetter($baseColumns + $dayColumns + 5);
                    for ($row = 7; $row <= $lastRow; $row++) {
                        $rateValue = $sheet->getCell($attendanceRateCol . $row)->getValue();
                        $rate = (float) str_replace('%', '', $rateValue);
                        if ($rate < 80 && $rate > 0) {
                            // Highlight summary columns only
                            $summaryStartCol = $this->getColumnLetter($baseColumns + $dayColumns + 1);
                            $sheet->getStyle($summaryStartCol . $row . ':' . $lastColumn . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'FFE6E6'],
                                ],
                            ]);
                        }
                    }
                }

                // Add summary row
                $summaryRow = $lastRow + 2;
                $sheet->setCellValue('A' . $summaryRow, 'الإجمالي');

                // Merge cells for summary label
                $summaryLabelEndCol = $this->getColumnLetter($baseColumns + $dayColumns);
                $sheet->mergeCells('A' . $summaryRow . ':' . $summaryLabelEndCol . $summaryRow);

                // Summary values
                $summaryStartCol = $this->getColumnLetter($baseColumns + $dayColumns + 1);
                $sheet->setCellValue($summaryStartCol . $summaryRow, $totalPresent);
                $sheet->setCellValue($this->getColumnLetter($baseColumns + $dayColumns + 2) . $summaryRow, $totalAbsent);
                $sheet->setCellValue($this->getColumnLetter($baseColumns + $dayColumns + 3) . $summaryRow, $totalLate);
                $sheet->setCellValue($this->getColumnLetter($baseColumns + $dayColumns + 4) . $summaryRow, $totalLeave);
                $sheet->setCellValue($attendanceRateCol . $summaryRow, $avgRate . '%');

                // Style summary row
                $summaryStyle = [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '70AD47'], // Green
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

                $sheet->getStyle('A' . $summaryRow . ':' . $lastColumn . $summaryRow)->applyFromArray($summaryStyle);

                // Set column widths
                // Base columns
                foreach (range(1, $baseColumns) as $col) {
                    $colLetter = $this->getColumnLetter($col);
                    $sheet->getColumnDimension($colLetter)->setWidth(15);
                }

                // Day columns (narrower)
                foreach (range($baseColumns + 1, $baseColumns + $dayColumns) as $col) {
                    $colLetter = $this->getColumnLetter($col);
                    $sheet->getColumnDimension($colLetter)->setWidth(4);
                }

                // Summary columns
                foreach (range($baseColumns + $dayColumns + 1, $baseColumns + $dayColumns + $summaryColumns) as $col) {
                    $colLetter = $this->getColumnLetter($col);
                    $sheet->getColumnDimension($colLetter)->setWidth(12);
                }
            },
        ];
    }

    private function getColumnLetter($columnNumber)
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = intval($columnNumber / 26);
        }
        return $letter;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function title(): string
    {
        return 'تقرير الحضور العام';
    }
}
