<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockMovementMethodologySheet implements FromArray, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Panduan';
    }

    public function array(): array
    {
        return [
            ['PANDUAN MEMBACA LAPORAN', '', ''],
            ['Istilah', 'Definisi', 'Pemanfaatan'],
            ['Qty Keluar', 'Jumlah barang keluar operasional pada periode terpilih. Retur dan perpindahan internal antar-gudang tidak dihitung.', 'Mengukur permintaan aktual yang dilayani gudang.'],
            ['Fast moving', 'SKU pada lapisan kontribusi kumulatif awal hingga 70% dari total qty keluar.', 'Jaga ketersediaan, safety stock, dan lead time replenishment.'],
            ['Medium moving', 'SKU pada lapisan kontribusi kumulatif berikutnya, dari 70% hingga 90%.', 'Monitor rutin dan sesuaikan frekuensi pembelian.'],
            ['Slow moving', 'SKU yang masih bergerak pada sisa kontribusi setelah 90%.', 'Batasi overstock dan evaluasi jumlah pembelian.'],
            ['Non-moving', 'SKU tanpa barang keluar operasional selama periode terpilih.', 'Evaluasi promo, transfer stok, atau penghentian pembelian.'],
            ['Rata-rata / Hari', 'Qty keluar dibagi jumlah hari kalender dalam periode.', 'Dasar estimasi konsumsi harian.'],
            ['Days Cover', 'Stok saat ini dibagi rata-rata qty keluar per hari. Kosong jika tidak ada pergerakan.', 'Estimasi berapa hari stok dapat memenuhi laju keluar historis.'],
            ['Kontribusi', 'Persentase qty keluar sebuah SKU terhadap total qty keluar seluruh SKU sesuai filter dasar.', 'Menunjukkan bobot SKU dalam aktivitas outbound.'],
            ['Di Bawah Safety', 'Stok saat ini kurang dari atau sama dengan safety stock, dengan safety stock lebih dari nol.', 'Pemicu pemeriksaan kebutuhan replenishment.'],
            ['Cover <= 7 Hari', 'SKU bergerak yang stoknya diperkirakan cukup paling lama tujuh hari.', 'Prioritas untuk verifikasi stok dan pengadaan.'],
            ['', '', ''],
            ['CATATAN', '', ''],
            ['Klasifikasi bersifat relatif terhadap SKU lain dalam filter dan periode yang sama. Perubahan filter atau periode dapat mengubah kelas sebuah SKU.', '', ''],
            ['Gunakan rekomendasi sebagai indikator awal. Keputusan pembelian tetap perlu mempertimbangkan lead time, pesanan berjalan, promosi, dan kapasitas gudang.', '', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A14:C14');
        $sheet->mergeCells('A15:C15');
        $sheet->mergeCells('A16:C16');
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(76);
        $sheet->getColumnDimension('C')->setWidth(54);
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 17, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach ([2, 14] as $row) {
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $sheet->getStyle('A1:C16')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('A2:C12')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        foreach (range(3, 12) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(46);
        }
        $sheet->getRowDimension(15)->setRowHeight(34);
        $sheet->getRowDimension(16)->setRowHeight(34);
        $sheet->freezePane('A3');

        return [];
    }
}
