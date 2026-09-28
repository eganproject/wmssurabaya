<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\Uom;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ItemsBulkUpdateTemplateExport implements WithMultipleSheets
{
    /**
     * @param  array<int,string>  $fields  Field terpilih (sudah dinormalisasi).
     * @param  Collection  $items  Item yang dipakai untuk mengisi template; kosong berarti template kosong.
     */
    public function __construct(
        private readonly array $fields,
        private readonly Collection $items,
        private readonly int $smallWarehouseId,
        private readonly int $largeWarehouseId,
    ) {}

    public function sheets(): array
    {
        $categories = Category::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();
        $uoms = Uom::where('is_active', true)->orderBy('code')->get(['code', 'name']);

        return [
            new ItemsBulkUpdateDataSheet(
                $this->fields,
                $this->items,
                $this->smallWarehouseId,
                $this->largeWarehouseId,
                $categories->count(),
                $uoms->count(),
            ),
            new ItemsBulkUpdateGuideSheet($this->fields),
            new ItemsBulkUpdateReferenceSheet($categories, $uoms),
        ];
    }
}
