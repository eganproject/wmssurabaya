<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockForecastMethodologySheet implements FromArray, WithStyles, WithTitle
{
    public function __construct(private readonly array $summary = []) {}

    public function title(): string
    {
        return 'Panduan Metodologi';
    }

    public function array(): array
    {
        return [
            ['PANDUAN ANALISA FORECAST & PENGADAAN', '', ''],
            ['Bagian', 'Definisi / Rumus', 'Cara Menggunakan'],
            ['Sumber demand', 'Outbound manual dan import resi yang sudah selesai diproses pada gudang terpilih.', 'Memastikan forecast berasal dari barang yang benar-benar keluar untuk memenuhi permintaan.'],
            ['Forecast harian', 'Weighted moving average: 50% laju 30 hari terbaru + 30% periode sebelumnya + 20% periode lebih lama. Bobot dinormalisasi jika histori belum lengkap.', 'Lebih responsif terhadap demand terbaru tanpa mengabaikan pola periode sebelumnya.'],
            ['Posisi stok', 'Stok saat ini + transfer masuk yang berstatus shipped.', 'Menghindari pengadaan berlebih karena barang yang sedang dikirim ikut diperhitungkan.'],
            ['Days cover', 'Posisi stok ÷ forecast kebutuhan per hari.', 'Menunjukkan estimasi berapa hari stok dapat melayani demand.'],
            ['Lead time', 'Import memakai '.($this->summary['import_lead_days'] ?? 90).' hari; Nanggewer memakai '.($this->summary['production_lead_days'] ?? 14).' hari pada export ini.', 'Sumber pengadaan pada master item menentukan lead time yang aktif.'],
            ['Siklus review', ($this->summary['review_days'] ?? 30).' hari pada export ini. Jarak waktu sampai evaluasi/pengadaan berikutnya.', 'Target harus cukup hingga barang datang dan sampai kesempatan review berikutnya.'],
            ['Target hari', 'Lead time + siklus review.', 'Horizon persediaan yang ingin dicapai setelah pengadaan.'],
            ['Target qty', 'Pembulatan ke atas dari forecast harian × target hari.', 'Kebutuhan kuantitas pada seluruh horizon target.'],
            ['Rekomendasi qty', 'Maksimum antara 0 dan target qty − posisi stok.', 'Jumlah yang disarankan untuk menutup kebutuhan horizon. Tidak memakai safety stock.'],
            ['Pembulatan Import', 'Rekomendasi item Import dibulatkan ke kelipatan isi kemasan jika master item memiliki UOM kemasan.', 'Menghasilkan kuantitas yang realistis untuk pembelian Import.'],
            ['Order Sekarang', 'Days cover kurang dari atau sama dengan lead time sumber.', 'Periksa PO/perintah produksi berjalan dan percepat keputusan pengadaan.'],
            ['Jadwalkan', 'Stok melewati lead time, tetapi belum mencukupi target lead time + review.', 'Gunakan tanggal order rekomendasi untuk menyusun jadwal.'],
            ['Tercukupi', 'Posisi stok masih memenuhi target horizon.', 'Belum memerlukan penambahan berdasarkan parameter saat ini.'],
            ['Tanpa Demand', 'Forecast harian nol karena tidak ada outbound valid.', 'Validasi produk baru, demand musiman, atau kebutuhan bisnis sebelum melakukan pengadaan.'],
            ['', '', ''],
            ['KUALITAS DATA', '', ''],
            ['Baik', 'Outbound tercatat pada minimal 20 hari aktif dalam histori.', 'Forecast lebih layak dijadikan dasar, tetapi tetap cek promo dan perubahan bisnis.'],
            ['Cukup', 'Outbound tercatat pada 7–19 hari aktif.', 'Gabungkan forecast dengan pengetahuan operasional.'],
            ['Rendah', 'Outbound tercatat kurang dari 7 hari aktif.', 'Wajib validasi manual karena demand jarang dan mudah terdistorsi.'],
            ['Tidak Ada', 'Tidak ada demand yang dapat digunakan.', 'Jangan menjalankan rekomendasi otomatis tanpa alasan bisnis.'],
            ['', '', ''],
            ['CATATAN KEPUTUSAN', '', ''],
            ['Forecast adalah alat bantu keputusan, bukan keputusan final. Pertimbangkan promosi, musiman, minimum order quantity, kapasitas gudang, anggaran, PO berjalan, risiko supplier, dan perubahan harga.', '', ''],
            ['Perubahan histori, lead time, siklus review, gudang, atau sumber pengadaan akan mengubah hasil rekomendasi.', '', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ([1, 18, 24, 25, 26] as $row) {
            $sheet->mergeCells("A{$row}:C{$row}");
        }

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(80);
        $sheet->getColumnDimension('C')->setWidth(62);
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        foreach ([2, 18, 24] as $row) {
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $sheet->getStyle('A1:C26')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('A2:C16')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('A19:C22')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        foreach (array_merge(range(3, 16), range(19, 22)) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(48);
        }
        $sheet->getRowDimension(25)->setRowHeight(48);
        $sheet->getRowDimension(26)->setRowHeight(34);
        $sheet->freezePane('A3');
        $sheet->setShowGridlines(false);

        return [];
    }
}
