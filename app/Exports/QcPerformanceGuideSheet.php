<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QcPerformanceGuideSheet implements FromArray, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Panduan';
    }

    public function array(): array
    {
        return [
            ['PANDUAN MEMBACA LAPORAN PERFORMA QC', '', ''],
            ['Indikator', 'Definisi', 'Cara Menggunakan'],
            ['Jam Aktif', 'Jumlah bucket jam yang memiliki minimal satu aktivitas QC. Ini bukan durasi shift atau absensi.', 'Gunakan sebagai denominator produktivitas agar waktu tanpa aktivitas tidak dianggap sebagai jam kerja QC.'],
            ['Resi/Jam Aktif', 'Total resi yang mulai diproses dibagi jumlah jam aktif.', 'Bandingkan akun pada periode dan jenis beban kerja yang setara.'],
            ['Qty/Jam Aktif', 'Total qty item yang terverifikasi dibagi jumlah jam aktif.', 'Melengkapi resi/jam ketika kompleksitas isi resi berbeda.'],
            ['Completion', 'Persentase resi berstatus selesai terhadap seluruh resi yang mulai diproses.', 'Nilai tinggi menunjukkan sedikit pekerjaan yang tertahan.'],
            ['Kesesuaian Qty', 'Qty hasil scan dibagi qty wajib pada seluruh SKU lines.', 'Gunakan untuk memantau progres verifikasi kuantitas.'],
            ['Rata-rata Durasi', 'Rata-rata selisih waktu mulai scan hingga selesai, hanya untuk resi selesai dan timestamp valid.', 'Baca bersama qty/resi; pesanan kompleks wajar membutuhkan waktu lebih lama.'],
            ['Jam Tersibuk', 'Jam dengan jumlah resi mulai diproses paling banyak.', 'Berguna untuk mengatur jadwal, kapasitas perangkat, dan dukungan operasional.'],
            ['', '', ''],
            ['CATATAN ANALISIS', '', ''],
            ['Produktivitas bukan satu-satunya ukuran kualitas. Selalu evaluasi completion, kesesuaian qty, kompleksitas pesanan, dan kendala sistem secara bersamaan.', '', ''],
            ['Perbandingan antarakun sebaiknya memakai rentang tanggal dan karakteristik pekerjaan yang sama. Data pada jam dengan aktivitas rendah dapat menghasilkan rasio yang tampak ekstrem.', '', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ([1, 11, 12, 13] as $row) {
            $sheet->mergeCells("A{$row}:C{$row}");
        }
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(76);
        $sheet->getColumnDimension('C')->setWidth(62);
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 17, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach ([2, 11] as $row) {
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            ]);
        }
        $sheet->getStyle('A1:C13')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('A2:C9')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        foreach (range(3, 9) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(48);
        }
        $sheet->freezePane('A3');

        return [];
    }
}
