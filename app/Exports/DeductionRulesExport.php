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

class DeductionRulesExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithCustomStartCell, WithEvents
{
    protected $person;
    protected $deductions;
    protected $startDate;
    protected $endDate;

    public function __construct($person, $deductions, $startDate, $endDate)
    {
        $this->person = $person;
        $this->deductions = $deductions;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function title(): string
    {
        return 'الخصومات المطبقة';
    }

    public function headings(): array
    {
        return [
            'اسم القاعدة',
            'نوع الخصم',
            'مبلغ الخصم (دينار)',
            'عدد الأيام المكتشفة',
            'سبب التطبيق',
            'التاريخ',
            'اليوم',
            'تفاصيل الحدث',
        ];
    }

    public function array(): array
    {
        $data = [];

        if (isset($this->deductions['applied_deductions']) && count($this->deductions['applied_deductions']) > 0) {
            foreach ($this->deductions['applied_deductions'] as $deduction) {
                $deductionTypeLabels = [
                    'fixed' => 'مبلغ ثابت',
                    'percentage' => 'نسبة مئوية',
                    'daily_salary' => 'يوم/أيام من المرتب',
                    'hourly_salary' => 'ساعات من المرتب',
                ];

                $deductionType = $deductionTypeLabels[$deduction['deduction_type']] ?? $deduction['deduction_type'];

                if (isset($deduction['triggered_days']) && count($deduction['triggered_days']) > 0) {
                    foreach ($deduction['triggered_days'] as $day) {
                        $data[] = [
                            $deduction['rule']['name'],
                            $deductionType,
                            $deduction['deduction_amount'],
                            count($deduction['triggered_days']),
                            $deduction['reason'] ?? '',
                            $day['date'],
                            $day['day_name'],
                            $day['details'] ?? '',
                        ];
                    }
                } else {
                    // If no triggered days, still show the deduction
                    $data[] = [
                        $deduction['rule']['name'],
                        $deductionType,
                        $deduction['deduction_amount'],
                        0,
                        $deduction['reason'] ?? '',
                        '-',
                        '-',
                        '-',
                    ];
                }
            }
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 40, // اسم القاعدة
            'B' => 25, // نوع الخصم
            'C' => 20, // مبلغ الخصم
            'D' => 20, // عدد الأيام
            'E' => 40, // سبب التطبيق
            'F' => 15, // التاريخ
            'G' => 15, // اليوم
            'H' => 30, // تفاصيل الحدث
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header row style
        $sheet->getStyle('A5:H5')->applyFromArray([
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

        // Info section styles
        $sheet->getStyle('A1:H3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
        ]);

        // Data rows style
        $lastRow = $sheet->getHighestRow();
        if ($lastRow > 5) {
            $sheet->getStyle('A6:H' . $lastRow)->applyFromArray([
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
            for ($row = 6; $row <= $lastRow; $row++) {
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F2F2F2'],
                        ],
                    ]);
                }
            }
        }

        // Summary section
        $summaryRow = $lastRow + 2;
        $totalDeduction = $this->deductions['total_deduction'] ?? 0;
        $sheet->setCellValue('A' . $summaryRow, 'إجمالي الخصومات:');
        $sheet->setCellValue('B' . $summaryRow, $totalDeduction . ' دينار');
        $sheet->mergeCells('B' . $summaryRow . ':H' . $summaryRow);
        $sheet->getStyle('A' . $summaryRow . ':H' . $summaryRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFE699'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
        ]);

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Add header information
                $sheet->setCellValue('A1', 'اسم الموظف/المعلم:');
                $sheet->setCellValue('B1', $this->person['name']);
                $sheet->setCellValue('A2', 'نوع الموظف:');
                $sheet->setCellValue('B2', $this->person['type_label']);
                $sheet->setCellValue('A3', 'الفترة:');
                $sheet->setCellValue('B3', $this->startDate . ' إلى ' . $this->endDate);

                // Merge cells for better layout
                $sheet->mergeCells('B1:H1');
                $sheet->mergeCells('B2:H2');
                $sheet->mergeCells('B3:H3');

                // Style header info
                $sheet->getStyle('A1:A3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);

                $sheet->getStyle('B1:H3')->applyFromArray([
                    'font' => ['size' => 12],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
            },
        ];
    }
}

