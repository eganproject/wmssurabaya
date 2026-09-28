<?php

namespace App\Exports;

use App\Exports\Concerns\BindsStringValuesAsText;
use App\Models\Item;
use App\Support\ItemBulkUpdateFields;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ItemsBulkUpdateDataSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithStyles, WithTitle
{
    use BindsStringValuesAsText;

    /** Minimal jumlah baris yang diberi dropdown agar baris tambahan tetap terbantu. */
    private const MIN_VALIDATION_ROWS = 500;

    private ?array $columns = null;

    public function __construct(
        private readonly array $fields,
        private readonly Collection $items,
        private readonly int $smallWarehouseId,
        private readonly int $largeWarehouseId,
        private readonly int $categoryCount,
        private readonly int $uomCount,
    ) {}

    public function title(): string
    {
        return 'Update Item';
    }

    /**
     * Kolom kunci (SKU) selalu di depan. Jika nama item tidak ikut diupdate,
     * nama saat ini disertakan sebagai kolom referensi supaya baris mudah dikenali.
     */
    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $columns = [ItemBulkUpdateFields::KEY_COLUMN];
        if (!in_array('name', $this->fields, true)) {
            $columns[] = ItemBulkUpdateFields::REFERENCE_COLUMN;
        }

        return $this->columns = array_merge($columns, $this->fields);
    }

    private function lockedColumnCount(): int
    {
        return count(array_intersect($this->columns(), [
            ItemBulkUpdateFields::KEY_COLUMN,
            ItemBulkUpdateFields::REFERENCE_COLUMN,
        ]));
    }

    public function array(): array
    {
        $rows = [$this->columns()];

        foreach ($this->items as $item) {
            $settings = $item->warehouseSettings->keyBy('warehouse_id');
            $small = $settings->get($this->smallWarehouseId);
            $large = $this->largeWarehouseId > 0 ? $settings->get($this->largeWarehouseId) : null;

            $rows[] = array_map(fn ($column) => match ($column) {
                ItemBulkUpdateFields::KEY_COLUMN => (string) $item->sku,
                ItemBulkUpdateFields::REFERENCE_COLUMN, 'name' => (string) $item->name,
                'category' => (string) ($item->category?->name ?? ''),
                'procurement_source' => (string) ($item->procurement_source ?: 'nanggewer'),
                'status' => $item->is_active ? 'aktif' : 'nonaktif',
                'description' => (string) ($item->description ?? ''),
                'base_unit' => $item->is_bundle ? '' : (string) ($item->units->firstWhere('is_base', true)?->name ?? 'PCS'),
                'koli_length_cm', 'koli_width_cm', 'koli_height_cm' => $item->{$column} ?? '',
                'small_warehouse_safety_stock' => (int) ($small?->safety_stock ?? 0),
                'small_warehouse_location' => (string) ($small?->location ?? ''),
                'large_warehouse_safety_stock' => (int) ($large?->safety_stock ?? 0),
                'large_warehouse_location' => (string) ($large?->location ?? ''),
                default => '',
            }, $this->columns());
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $columns = $this->columns();
        $fields = ItemBulkUpdateFields::all();
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $lastDataRow = max(2, $this->items->count() + 1);
        $validationLastRow = max($lastDataRow, self::MIN_VALIDATION_ROWS + 1);
        $lockedCount = $this->lockedColumnCount();
        $lastLockedColumn = Coordinate::stringFromColumnIndex($lockedCount);

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        // Kolom kunci/referensi diberi warna berbeda sebagai tanda tidak ikut diupdate.
        $sheet->getStyle("A1:{$lastLockedColumn}1")->getFill()->getStartColor()->setRGB('5E6278');
        $sheet->getStyle("A2:{$lastLockedColumn}{$lastDataRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F1F4']],
            'font' => ['color' => ['rgb' => '4B5675']],
        ]);
        $sheet->getStyle("A1:{$lastColumn}{$lastDataRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('C4CADA');

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth(match ($column) {
                ItemBulkUpdateFields::KEY_COLUMN => 22,
                ItemBulkUpdateFields::REFERENCE_COLUMN, 'name' => 40,
                'description' => 48,
                'category' => 28,
                default => 24,
            });

            $comment = match ($column) {
                ItemBulkUpdateFields::KEY_COLUMN => 'Kunci pencarian item. SKU tidak dapat diubah lewat import ini.',
                ItemBulkUpdateFields::REFERENCE_COLUMN => 'Nama item saat ini, hanya sebagai referensi. Kolom ini diabaikan saat import.',
                default => $fields[$column]['label'].': '.$fields[$column]['hint'],
            };
            $sheet->getComment("{$letter}1")->setWidth('240pt')->setHeight('70pt')
                ->getText()->createTextRun($comment);

            if (in_array($column, ['small_warehouse_safety_stock', 'large_warehouse_safety_stock'], true)) {
                $sheet->getStyle("{$letter}2:{$letter}{$validationLastRow}")->getNumberFormat()->setFormatCode('0');
                $this->applyValidation($sheet, "{$letter}2:{$letter}{$validationLastRow}", DataValidation::TYPE_WHOLE, null, 'Safety stock harus berupa angka bulat 0 atau lebih.');
            } elseif (in_array($column, Item::KOLI_DIMENSION_FIELDS, true)) {
                $sheet->getStyle("{$letter}2:{$letter}{$validationLastRow}")->getNumberFormat()->setFormatCode('0.##');
                $this->applyValidation($sheet, "{$letter}2:{$letter}{$validationLastRow}", DataValidation::TYPE_DECIMAL, null, 'Dimensi koli harus berupa angka lebih dari 0 (cm).');
            } elseif (in_array($column, [ItemBulkUpdateFields::KEY_COLUMN, 'small_warehouse_location', 'large_warehouse_location'], true)) {
                $sheet->getStyle("{$letter}2:{$letter}{$validationLastRow}")->getNumberFormat()->setFormatCode('@');
            }

            $list = match ($column) {
                'status' => '"aktif,nonaktif"',
                'procurement_source' => '"nanggewer,import"',
                'category' => $this->categoryCount > 0 ? "'Referensi'!\$A\$2:\$A\$".($this->categoryCount + 1) : null,
                'base_unit' => $this->uomCount > 0 ? "'Referensi'!\$C\$2:\$C\$".($this->uomCount + 1) : null,
                default => null,
            };
            if ($list !== null) {
                $this->applyValidation($sheet, "{$letter}2:{$letter}{$validationLastRow}", DataValidation::TYPE_LIST, $list, 'Pilih nilai dari daftar yang tersedia.');
            }
        }

        $sheet->freezePane(Coordinate::stringFromColumnIndex($lockedCount + 1).'2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastDataRow}");
        $sheet->setSelectedCell('A2');

        return [];
    }

    private function applyValidation(Worksheet $sheet, string $range, string $type, ?string $formula, string $message): void
    {
        $validation = (new DataValidation())
            ->setType($type)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Nilai tidak valid')
            ->setError($message);

        if ($type === DataValidation::TYPE_LIST) {
            $validation->setShowDropDown(true)->setFormula1($formula);
        } else {
            $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)->setFormula1('0');
        }

        $sheet->setDataValidation($range, $validation);
    }
}
