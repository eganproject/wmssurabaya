<?php

namespace App\Support;

/**
 * Daftar field master item yang boleh diperbarui massal lewat import Excel.
 * SKU dipakai sebagai kunci pencarian, sedangkan informasi koli (satuan kemasan
 * dan isi per koli) sengaja tidak tersedia di sini agar tidak bisa diubah.
 * Dimensi koli boleh diubah karena hanya informasi CBM dan tidak memengaruhi stok.
 */
class ItemBulkUpdateFields
{
    public const KEY_COLUMN = 'sku';

    /** Kolom bantu berisi nama item saat ini; hanya referensi, diabaikan saat import. */
    public const REFERENCE_COLUMN = 'reference_name';

    public static function all(): array
    {
        return [
            'name' => [
                'label' => 'Nama Item',
                'group' => 'Identitas Item',
                'hint' => 'Wajib diisi, maksimal 150 karakter.',
            ],
            'category' => [
                'label' => 'Kategori',
                'group' => 'Identitas Item',
                'hint' => 'Nama kategori yang sudah terdaftar. Kosongkan untuk "Tanpa Kategori".',
            ],
            'procurement_source' => [
                'label' => 'Sumber Pengadaan',
                'group' => 'Identitas Item',
                'hint' => 'Isi nanggewer (produksi) atau import.',
            ],
            'status' => [
                'label' => 'Status Produk',
                'group' => 'Identitas Item',
                'hint' => 'Isi aktif atau nonaktif.',
            ],
            'description' => [
                'label' => 'Deskripsi',
                'group' => 'Identitas Item',
                'hint' => 'Teks bebas. Kosongkan untuk menghapus deskripsi.',
            ],
            'base_unit' => [
                'label' => 'UOM Dasar',
                'group' => 'Satuan',
                'hint' => 'Kode UOM aktif, misal PCS atau SET. Tidak berlaku untuk item bundle.',
            ],
            'koli_length_cm' => [
                'label' => 'Panjang Koli (cm)',
                'group' => 'Dimensi Koli',
                'hint' => 'Angka > 0 dalam cm, maksimal 2 desimal. Kosongkan untuk menghapus.',
            ],
            'koli_width_cm' => [
                'label' => 'Lebar Koli (cm)',
                'group' => 'Dimensi Koli',
                'hint' => 'Angka > 0 dalam cm, maksimal 2 desimal. Kosongkan untuk menghapus.',
            ],
            'koli_height_cm' => [
                'label' => 'Tinggi Koli (cm)',
                'group' => 'Dimensi Koli',
                'hint' => 'Angka > 0 dalam cm, maksimal 2 desimal. Kosongkan untuk menghapus.',
            ],
            'small_warehouse_safety_stock' => [
                'label' => 'Safety Stock Gudang Kecil',
                'group' => 'Gudang Kecil',
                'hint' => 'Angka bulat ≥ 0 dalam satuan dasar. Kosong dianggap 0.',
            ],
            'small_warehouse_location' => [
                'label' => 'Lokasi Gudang Kecil',
                'group' => 'Gudang Kecil',
                'hint' => 'Kode rak/lokasi. Kosongkan untuk menghapus lokasi.',
            ],
            'large_warehouse_safety_stock' => [
                'label' => 'Safety Stock Gudang Besar',
                'group' => 'Gudang Besar',
                'hint' => 'Angka bulat ≥ 0 dalam satuan dasar. Kosong dianggap 0.',
            ],
            'large_warehouse_location' => [
                'label' => 'Lokasi Gudang Besar',
                'group' => 'Gudang Besar',
                'hint' => 'Kode blok/lokasi. Kosongkan untuk menghapus lokasi.',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function grouped(): array
    {
        $groups = [];
        foreach (self::all() as $key => $field) {
            $groups[$field['group']][$key] = $field;
        }

        return $groups;
    }

    /**
     * Normalisasi pilihan user: buang key tidak dikenal, hilangkan duplikat,
     * dan kembalikan dengan urutan baku agar kolom template konsisten.
     */
    public static function normalize(array $fields): array
    {
        $selected = array_map(fn ($field) => trim((string) $field), $fields);

        return array_values(array_filter(self::keys(), fn ($key) => in_array($key, $selected, true)));
    }
}
