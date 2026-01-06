<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

class PayrollPreviewExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithCustomStartCell, WithEvents
{
    protected $previewData;
    protected $month;
    protected $year;

    public function __construct(array $previewData)
    {
        $this->previewData = $previewData;
        $this->month = $previewData['month'];
        $this->year = $previewData['year'];
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function title(): string
    {
        return 'معاينة الرواتب';
    }

    public function headings(): array
    {
        return [
            'الاسم الكامل',
            'النوع',
            'الراتب الإجمالي',
            'قاعدة الخصم 1',
            'مبلغ الخصم 1',
            'عدد الأيام 1',
            'قاعدة الخصم 2',
            'مبلغ الخصم 2',
            'عدد الأيام 2',
            'قاعدة الخصم 3',
            'مبلغ الخصم 3',
            'عدد الأيام 3',
            'إجمالي الخصومات',
            'صافي الراتب',
        ];
    }

    public function array(): array
    {
        $data = [];
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $monthName = $months[$this->month] ?? '';

        if (isset($this->previewData['preview']) && is_array($this->previewData['preview'])) {
            foreach ($this->previewData['preview'] as $item) {
                // Use full_name if available, otherwise use name
                $personName = $item['full_name'] ?? $item['name'] ?? '';
                $personType = $item['type'] === 'employee' ? 'موظف' : 'معلم';
                $grossSalary = number_format((float)($item['gross_salary'] ?? 0), 2);
                $totalDeduction = number_format((float)($item['deductions']['total_deduction'] ?? 0), 2);
                $netSalary = number_format((float)($item['apply_deductions'] ? ($item['net_salary'] ?? 0) : ($item['gross_salary'] ?? 0)), 2);

                // Initialize deduction columns (up to 3 deduction rules)
                $deduction1Name = '-';
                $deduction1Amount = '-';
                $deduction1Days = '-';
                $deduction2Name = '-';
                $deduction2Amount = '-';
                $deduction2Days = '-';
                $deduction3Name = '-';
                $deduction3Amount = '-';
                $deduction3Days = '-';

                // Process deductions - one row per person
                if (isset($item['deductions']['applied_deductions']) && count($item['deductions']['applied_deductions']) > 0) {
                    $deductionIndex = 1;
                    foreach ($item['deductions']['applied_deductions'] as $deduction) {
                        if ($deductionIndex > 3) break; // Limit to 3 deductions per person
                        
                        $ruleName = $deduction['rule']['name'] ?? 'قاعدة خصم';
                        $deductionAmount = number_format((float)($deduction['deduction_amount'] ?? 0), 2);
                        $triggeredCount = $deduction['triggered_count'] ?? 0;
                        
                        // If deduction has groups, show total groups count
                        if (isset($deduction['groups']) && count($deduction['groups']) > 0) {
                            $totalGroups = $deduction['total_groups'] ?? count($deduction['groups']);
                            $triggeredCount = $totalGroups > 0 ? $totalGroups . ' مجموعة' : $triggeredCount;
                        }

                        switch ($deductionIndex) {
                            case 1:
                                $deduction1Name = $ruleName;
                                $deduction1Amount = $deductionAmount . ' دينار';
                                $deduction1Days = $triggeredCount;
                                break;
                            case 2:
                                $deduction2Name = $ruleName;
                                $deduction2Amount = $deductionAmount . ' دينار';
                                $deduction2Days = $triggeredCount;
                                break;
                            case 3:
                                $deduction3Name = $ruleName;
                                $deduction3Amount = $deductionAmount . ' دينار';
                                $deduction3Days = $triggeredCount;
                                break;
                        }
                        $deductionIndex++;
                    }
                }

                // One row per person
                $data[] = [
                    $personName,
                    $personType,
                    $grossSalary . ' دينار',
                    $deduction1Name,
                    $deduction1Amount,
                    $deduction1Days,
                    $deduction2Name,
                    $deduction2Amount,
                    $deduction2Days,
                    $deduction3Name,
                    $deduction3Amount,
                    $deduction3Days,
                    $totalDeduction . ' دينار',
                    $netSalary . ' دينار',
                ];
            }
        }

        return $data;
    }

    private function parseEventDetails($detailsString): array
    {
        $result = ['late' => '-', 'early_leave' => '-'];
        
        if (empty($detailsString)) {
            return $result;
        }

        // Parse "تأخير: X ساعة و Y دقيقة" or "تأخير: X دقيقة"
        if (preg_match('/تأخير:\s*([^،]+)/u', $detailsString, $matches)) {
            $result['late'] = $this->formatEventDetails(trim($matches[1]));
        }

        // Parse "انصراف مبكر: X ساعة و Y دقيقة" or "انصراف مبكر: X دقيقة"
        if (preg_match('/انصراف مبكر:\s*([^،]+)/u', $detailsString, $matches)) {
            $result['early_leave'] = $this->formatEventDetails(trim($matches[1]));
        }

        return $result;
    }

    private function formatEventDetails(string $detailsString): string
    {
        $parts = explode('، ', $detailsString);
        $formattedParts = [];
        foreach ($parts as $part) {
            if (str_contains($part, 'تأخير:') || str_contains($part, 'انصراف مبكر:')) {
                list($type, $valueStr) = explode(': ', $part);
                $minutes = (int) filter_var($valueStr, FILTER_SANITIZE_NUMBER_INT);
                if ($minutes < 60) {
                    $formattedParts[] = "{$type}: {$minutes} دقيقة";
                } else {
                    $hours = floor($minutes / 60);
                    $remainingMinutes = $minutes % 60;
                    $formattedParts[] = "{$type}: {$hours} ساعة" . ($remainingMinutes > 0 ? " و{$remainingMinutes} دقيقة" : "");
                }
            } else {
                $formattedParts[] = $part;
            }
        }
        return implode('، ', $formattedParts);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // الاسم
            'B' => 15, // النوع
            'C' => 20, // الراتب الإجمالي
            'D' => 40, // قاعدة الخصم 1
            'E' => 18, // مبلغ الخصم 1
            'F' => 15, // عدد الأيام 1
            'G' => 40, // قاعدة الخصم 2
            'H' => 18, // مبلغ الخصم 2
            'I' => 15, // عدد الأيام 2
            'J' => 40, // قاعدة الخصم 3
            'K' => 18, // مبلغ الخصم 3
            'L' => 15, // عدد الأيام 3
            'M' => 20, // إجمالي الخصومات
            'N' => 20, // صافي الراتب
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header row style
        $sheet->getStyle('A6:N6')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
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
        ]);

        // Data rows style
        $lastRow = $sheet->getHighestRow();
        if ($lastRow > 6) {
            $sheet->getStyle('A7:N' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Alternate row colors
            for ($row = 7; $row <= $lastRow; $row++) {
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':N' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2'],
                        ],
                    ]);
                }
            }

            // Highlight deduction amounts (columns E, H, K, M)
            for ($row = 7; $row <= $lastRow; $row++) {
                foreach (['E', 'H', 'K', 'M'] as $col) {
                    $deductionValue = $sheet->getCell($col . $row)->getValue();
                    if ($deductionValue && $deductionValue !== '0.00 دينار' && $deductionValue !== '-') {
                        $sheet->getStyle($col . $row)->applyFromArray([
                            'font' => [
                                'color' => ['rgb' => 'DC2626'],
                                'bold' => true,
                            ],
                        ]);
                    }
                }
            }

            // Highlight net salary column (column N)
            $sheet->getStyle('N7:N' . $lastRow)->applyFromArray([
                'font' => [
                    'color' => ['rgb' => '059669'],
                    'bold' => true,
                ],
            ]);
        }

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $months = [
                    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
                ];
                $monthName = $months[$this->month] ?? '';

                // Add header information
                $sheet->setCellValue('A1', 'تقرير معاينة الرواتب');
                $sheet->setCellValue('A2', 'الفترة:');
                $sheet->setCellValue('B2', $monthName . ' ' . $this->year);
                $sheet->setCellValue('A3', 'تاريخ التصدير:');
                $sheet->setCellValue('B3', date('Y-m-d H:i:s'));

                // Merge cells for header
                $sheet->mergeCells('A1:N1');
                $sheet->mergeCells('B2:N2');
                $sheet->mergeCells('B3:N3');

                // Style header
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A2:A3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);

                $sheet->getStyle('B2:N3')->applyFromArray([
                    'font' => ['size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);

                // Add summary section
                $lastRow = $sheet->getHighestRow();
                $summaryRow = $lastRow + 2;

                $totalGross = 0;
                $totalDeductions = 0;
                $totalNet = 0;
                $personCount = 0;

                if (isset($this->previewData['preview']) && is_array($this->previewData['preview'])) {
                    foreach ($this->previewData['preview'] as $item) {
                        $totalGross += (float)($item['gross_salary'] ?? 0);
                        $totalDeductions += (float)($item['deductions']['total_deduction'] ?? 0);
                        $totalNet += (float)($item['apply_deductions'] ? ($item['net_salary'] ?? 0) : ($item['gross_salary'] ?? 0));
                        $personCount++;
                    }
                }

                $sheet->setCellValue('A' . $summaryRow, 'ملخص إجمالي الرواتب');
                $sheet->mergeCells('A' . $summaryRow . ':N' . $summaryRow);
                $sheet->getStyle('A' . $summaryRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '10B981'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $summaryRow++;
                $sheet->setCellValue('A' . $summaryRow, 'إجمالي الرواتب:');
                $sheet->setCellValue('B' . $summaryRow, number_format($totalGross, 2) . ' دينار');
                $summaryRow++;
                $sheet->setCellValue('A' . $summaryRow, 'إجمالي الخصومات:');
                $sheet->setCellValue('B' . $summaryRow, number_format($totalDeductions, 2) . ' دينار');
                $summaryRow++;
                $sheet->setCellValue('A' . $summaryRow, 'صافي الإجمالي:');
                $sheet->setCellValue('B' . $summaryRow, number_format($totalNet, 2) . ' دينار');
                $summaryRow++;
                $sheet->setCellValue('A' . $summaryRow, 'عدد الموظفين/المعلمين:');
                $sheet->setCellValue('B' . $summaryRow, $personCount);

                $sheet->getStyle('A' . ($summaryRow - 3) . ':B' . $summaryRow)->applyFromArray([
                    'font' => ['size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ]);

                // Add detailed deduction days section
                $detailsRow = $summaryRow + 3;
                $sheet->setCellValue('A' . $detailsRow, 'تفاصيل الأيام التي تم تطبيق سياسات الخصم عليها');
                $sheet->mergeCells('A' . $detailsRow . ':N' . $detailsRow);
                $sheet->getStyle('A' . $detailsRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F59E0B'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $detailsRow++;
                // Add headers for details section
                $detailsHeaders = [
                    'A' . $detailsRow => 'الاسم الكامل',
                    'B' . $detailsRow => 'النوع',
                    'C' . $detailsRow => 'قاعدة الخصم',
                    'D' . $detailsRow => 'المجموعة',
                    'E' . $detailsRow => 'التاريخ',
                    'F' . $detailsRow => 'اليوم',
                    'G' . $detailsRow => 'التأخير',
                    'H' . $detailsRow => 'الانصراف المبكر',
                ];

                foreach ($detailsHeaders as $cell => $header) {
                    $sheet->setCellValue($cell, $header);
                }

                $sheet->getStyle('A' . $detailsRow . ':H' . $detailsRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'DC2626'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $detailsRow++;
                $startDetailsRow = $detailsRow;

                // Add detailed deduction days data
                if (isset($this->previewData['preview']) && is_array($this->previewData['preview'])) {
                    foreach ($this->previewData['preview'] as $item) {
                        $personName = $item['full_name'] ?? $item['name'] ?? '';
                        $personType = $item['type'] === 'employee' ? 'موظف' : 'معلم';

                        if (isset($item['deductions']['applied_deductions']) && count($item['deductions']['applied_deductions']) > 0) {
                            foreach ($item['deductions']['applied_deductions'] as $deduction) {
                                $ruleName = $deduction['rule']['name'] ?? 'قاعدة خصم';

                                // If deduction has groups
                                if (isset($deduction['groups']) && count($deduction['groups']) > 0) {
                                    foreach ($deduction['groups'] as $group) {
                                        foreach ($group['days'] as $day) {
                                            $details = $this->parseEventDetails($day['details'] ?? '');
                                            $sheet->setCellValue('A' . $detailsRow, $personName);
                                            $sheet->setCellValue('B' . $detailsRow, $personType);
                                            $sheet->setCellValue('C' . $detailsRow, $ruleName);
                                            $sheet->setCellValue('D' . $detailsRow, 'المجموعة ' . $group['group_number']);
                                            $sheet->setCellValue('E' . $detailsRow, $day['date']);
                                            $sheet->setCellValue('F' . $detailsRow, $day['day_name']);
                                            $sheet->setCellValue('G' . $detailsRow, $details['late'] ?? '-');
                                            $sheet->setCellValue('H' . $detailsRow, $details['early_leave'] ?? '-');
                                            $detailsRow++;
                                        }
                                    }
                                } else if (isset($deduction['triggered_days']) && count($deduction['triggered_days']) > 0) {
                                    // If no groups, show triggered days
                                    foreach ($deduction['triggered_days'] as $day) {
                                        $details = $this->parseEventDetails($day['details'] ?? '');
                                        $sheet->setCellValue('A' . $detailsRow, $personName);
                                        $sheet->setCellValue('B' . $detailsRow, $personType);
                                        $sheet->setCellValue('C' . $detailsRow, $ruleName);
                                        $sheet->setCellValue('D' . $detailsRow, '-');
                                        $sheet->setCellValue('E' . $detailsRow, $day['date']);
                                        $sheet->setCellValue('F' . $detailsRow, $day['day_name']);
                                        $sheet->setCellValue('G' . $detailsRow, $details['late'] ?? '-');
                                        $sheet->setCellValue('H' . $detailsRow, $details['early_leave'] ?? '-');
                                        $detailsRow++;
                                    }
                                }
                            }
                        }
                    }
                }

                // Style details data rows
                if ($detailsRow > $startDetailsRow) {
                    $endDetailsRow = $detailsRow - 1;
                    $sheet->getStyle('A' . $startDetailsRow . ':H' . $endDetailsRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC'],
                            ],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    // Alternate row colors for details
                    for ($row = $startDetailsRow; $row <= $endDetailsRow; $row++) {
                        if ($row % 2 == 0) {
                            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'FEF3C7'],
                                ],
                            ]);
                        }
                    }

                    // Set column widths for details section
                    $sheet->getColumnDimension('A')->setWidth(30);
                    $sheet->getColumnDimension('B')->setWidth(15);
                    $sheet->getColumnDimension('C')->setWidth(40);
                    $sheet->getColumnDimension('D')->setWidth(20);
                    $sheet->getColumnDimension('E')->setWidth(15);
                    $sheet->getColumnDimension('F')->setWidth(15);
                    $sheet->getColumnDimension('G')->setWidth(25);
                    $sheet->getColumnDimension('H')->setWidth(25);
                }
            },
        ];
    }
}

