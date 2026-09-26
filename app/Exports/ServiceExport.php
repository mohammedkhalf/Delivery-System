<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServiceExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithHeadings, WithStyles
{
    use Exportable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('file-exports.service-list', [
            'data' => $this->data,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'B' => 15,
            'C' => 40,
            'D' => 25,
            'F' => 25,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $count = $this->data['data']->count();
        $lastRow = $count + 3;

        $sheet->getStyle('A2:H2')->getFont()->setBold(true);

        $sheet->getStyle('A3:H3')->getFill()->applyFromArray([
            'fillType' => 'solid',
            'color' => ['rgb' => '9F9F9F'],
        ]);

        $sheet->setShowGridlines(false);

        return [
            "A1:H{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    public function setImage($worksheet)
    {
        $this->data['data']->each(function ($service, $index) use ($worksheet) {

            $row = $index + 4;

            $drawing = new Drawing();
            $drawing->setName($service->name);
            $drawing->setDescription($service->name);

            $imagePath = storage_path('app/public/service/' . $service->thumbnail);

            if (!is_file($imagePath)) {
                $imagePath = public_path('/assets/admin/img/160x160/img2.jpg');
            }

            $drawing->setPath($imagePath);
            $drawing->setHeight(25);
            $drawing->setCoordinates("B{$row}");
            $drawing->setWorksheet($worksheet);

            $drawing->setResizeProportional(true);
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(3);
        });
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet;
                $worksheet = $sheet->getDelegate();
                $highestRow = $worksheet->getHighestRow();

                // Alignment
                $sheet->getStyle("A1:H{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('D2:H2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Merge cells
                $sheet->mergeCells('A1:H1');
                $sheet->mergeCells('A2:C2');
                $sheet->mergeCells('D2:H2');

                // Row heights
                $worksheet->getRowDimension(1)->setRowHeight(50);
                $worksheet->getRowDimension(2)->setRowHeight(100);

                // Apply row height to data rows
                for ($i = 4; $i <= $highestRow; $i++) {
                    $worksheet->getRowDimension($i)->setRowHeight(30);
                }

                // Insert images
                $this->setImage($worksheet);
            },
        ];
    }

    public function headings(): array
    {
        return [
            '1'
        ];
    }
}
