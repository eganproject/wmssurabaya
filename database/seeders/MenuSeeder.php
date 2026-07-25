<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menuRows = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'route' => 'admin.dashboard', 'icon' => 'fa-solid fa-gauge-high', 'parent_slug' => null, 'sort_order' => 0],
            ['name' => 'Master Data', 'slug' => 'master-data', 'route' => null, 'icon' => 'fa-solid fa-database', 'parent_slug' => null, 'sort_order' => 10],
            ['name' => 'Inventory', 'slug' => 'inventory', 'route' => null, 'icon' => 'fa-solid fa-warehouse', 'parent_slug' => null, 'sort_order' => 12],
            ['name' => 'Inbound', 'slug' => 'inbound', 'route' => null, 'icon' => 'fa-solid fa-arrow-down', 'parent_slug' => null, 'sort_order' => 13],
            ['name' => 'Outbound', 'slug' => 'outbound', 'route' => null, 'icon' => 'fa-solid fa-arrow-up', 'parent_slug' => null, 'sort_order' => 14],
            ['name' => 'Laporan', 'slug' => 'reports', 'route' => null, 'icon' => 'fa-solid fa-chart-line', 'parent_slug' => null, 'sort_order' => 15],
            ['name' => 'Users', 'slug' => 'users', 'route' => 'admin.masterdata.users.index', 'icon' => 'fa-solid fa-users', 'parent_slug' => 'master-data', 'sort_order' => 20],
            ['name' => 'Roles', 'slug' => 'roles', 'route' => 'admin.masterdata.roles.index', 'icon' => 'fa-solid fa-user-shield', 'parent_slug' => 'master-data', 'sort_order' => 21],
            ['name' => 'Divisi', 'slug' => 'divisi', 'route' => 'admin.masterdata.divisi.index', 'icon' => 'fa-solid fa-people-group', 'parent_slug' => 'master-data', 'sort_order' => 21.4],
            ['name' => 'Kurir', 'slug' => 'kurir', 'route' => 'admin.masterdata.kurir.index', 'icon' => 'fa-solid fa-truck', 'parent_slug' => 'master-data', 'sort_order' => 21.45],
            ['name' => 'Categories', 'slug' => 'categories', 'route' => 'admin.masterdata.categories.index', 'icon' => 'fa-solid fa-sitemap', 'parent_slug' => 'master-data', 'sort_order' => 21.5],
            ['name' => 'UOM', 'slug' => 'uoms', 'route' => 'admin.masterdata.uoms.index', 'icon' => 'fa-solid fa-ruler-combined', 'parent_slug' => 'master-data', 'sort_order' => 21.55],
            ['name' => 'Items', 'slug' => 'items', 'route' => 'admin.masterdata.items.index', 'icon' => 'fa-solid fa-box', 'parent_slug' => 'master-data', 'sort_order' => 21.6],
            ['name' => 'Gudang', 'slug' => 'warehouses', 'route' => 'admin.masterdata.warehouses.index', 'icon' => 'fa-solid fa-warehouse', 'parent_slug' => 'master-data', 'sort_order' => 21.65],
            ['name' => 'Item Stocks', 'slug' => 'item-stocks', 'route' => 'admin.inventory.item-stocks.index', 'icon' => 'fa-solid fa-boxes-stacked', 'parent_slug' => 'inventory', 'sort_order' => 10],
            ['name' => 'Stock Mutations', 'slug' => 'stock-mutations', 'route' => 'admin.inventory.stock-mutations.index', 'icon' => 'fa-solid fa-right-left', 'parent_slug' => 'inventory', 'sort_order' => 11],
            ['name' => 'Transfer Antar Gudang', 'slug' => 'stock-transfers', 'route' => 'admin.inventory.stock-transfers.index', 'icon' => 'fa-solid fa-truck-ramp-box', 'parent_slug' => 'inventory', 'sort_order' => 11.5],
            ['name' => 'Stock Opname', 'slug' => 'stock-opname', 'route' => 'admin.inventory.stock-opname.index', 'icon' => 'fa-solid fa-clipboard-check', 'parent_slug' => 'inventory', 'sort_order' => 12],
            ['name' => 'Penyesuaian Stok', 'slug' => 'stock-adjustments', 'route' => 'admin.inventory.stock-adjustments.index', 'icon' => 'fa-solid fa-sliders', 'parent_slug' => 'inventory', 'sort_order' => 12.5],
            ['name' => 'Barang Rusak', 'slug' => 'damaged-goods', 'route' => 'admin.inventory.damaged-goods.index', 'icon' => 'fa-solid fa-triangle-exclamation', 'parent_slug' => 'inventory', 'sort_order' => 13],
            ['name' => 'Alokasi Barang Rusak', 'slug' => 'damaged-allocations', 'route' => 'admin.inventory.damaged-allocations.index', 'icon' => 'fa-solid fa-share-from-square', 'parent_slug' => 'inventory', 'sort_order' => 13.2],
            ['name' => 'Import Resi', 'slug' => 'resi-import', 'route' => 'admin.inventory.resi-import.index', 'icon' => 'fa-solid fa-file-import', 'parent_slug' => 'inventory', 'sort_order' => 13.5],
            ['name' => 'QC Transit', 'slug' => 'picker-transit', 'route' => 'admin.inventory.picker-transit.index', 'icon' => 'fa-solid fa-box-open', 'parent_slug' => 'inventory', 'sort_order' => 14],
            ['name' => 'Picking List', 'slug' => 'picking-list', 'route' => 'admin.inventory.picking-list.index', 'icon' => 'fa-solid fa-list-check', 'parent_slug' => 'inventory', 'sort_order' => 14.5],
            ['name' => 'Stores', 'slug' => 'stores', 'route' => 'admin.masterdata.stores.index', 'icon' => 'fa-solid fa-store', 'parent_slug' => 'master-data', 'sort_order' => 21.7],
            ['name' => 'Menus', 'slug' => 'menus', 'route' => 'admin.masterdata.menus.index', 'icon' => 'fa-solid fa-bars', 'parent_slug' => 'master-data', 'sort_order' => 22],
            ['name' => 'Permissions', 'slug' => 'permissions', 'route' => 'admin.masterdata.permissions.index', 'icon' => 'fa-solid fa-lock', 'parent_slug' => 'master-data', 'sort_order' => 23],
            ['name' => 'Penerimaan Barang', 'slug' => 'inbound-receiving', 'route' => 'admin.inbound.receipts.index', 'icon' => 'fa-solid fa-dolly', 'parent_slug' => 'inbound', 'sort_order' => 10],
            ['name' => 'Retur', 'slug' => 'inbound-return', 'route' => 'admin.inbound.returns.index', 'icon' => 'fa-solid fa-rotate-left', 'parent_slug' => 'inbound', 'sort_order' => 11],
            // ['name' => 'Picker', 'slug' => 'outbound-picker', 'route' => 'admin.outbound.pickers.index', 'icon' => 'fa-solid fa-people-carry-box', 'parent_slug' => 'outbound', 'sort_order' => 10],
            ['name' => 'Manual', 'slug' => 'outbound-manual', 'route' => 'admin.outbound.manuals.index', 'icon' => 'fa-solid fa-pen-to-square', 'parent_slug' => 'outbound', 'sort_order' => 11],
            ['name' => 'Retur', 'slug' => 'outbound-return', 'route' => 'admin.outbound.returns.index', 'icon' => 'fa-solid fa-rotate-left', 'parent_slug' => 'outbound', 'sort_order' => 12],
            ['name' => 'QC Scan Input', 'slug' => 'outbound-qc-scan', 'route' => 'admin.outbound.qc-scan.index', 'icon' => 'fa-solid fa-barcode', 'parent_slug' => 'outbound', 'sort_order' => 12.5],
            ['name' => 'Scan Out Input', 'slug' => 'outbound-scan-out', 'route' => 'admin.outbound.scan-out.index', 'icon' => 'fa-solid fa-truck-ramp-box', 'parent_slug' => 'outbound', 'sort_order' => 12.7],
            ['name' => 'History QC Scan', 'slug' => 'outbound-qc-scan-history', 'route' => 'admin.outbound.qc-scan-history.index', 'icon' => 'fa-solid fa-clipboard-list', 'parent_slug' => 'outbound', 'sort_order' => 13],
            ['name' => 'History Packing (Legacy)', 'slug' => 'outbound-packer-history', 'route' => 'admin.outbound.packer-history.index', 'icon' => 'fa-solid fa-box-archive', 'parent_slug' => 'outbound', 'sort_order' => 13.5, 'is_active' => false],
            ['name' => 'History Scan Out', 'slug' => 'outbound-packer-scan-outs', 'route' => 'admin.outbound.packer-scan-outs.index', 'icon' => 'fa-solid fa-truck-ramp-box', 'parent_slug' => 'outbound', 'sort_order' => 13.7],
            ['name' => 'SKU Exception Scan Out', 'slug' => 'outbound-packer-scan-exceptions', 'route' => 'admin.outbound.packer-scan-exceptions.index', 'icon' => 'fa-solid fa-ban', 'parent_slug' => 'outbound', 'sort_order' => 13.8],
            ['name' => 'Laporan QC Scan', 'slug' => 'outbound-picker-report', 'route' => 'admin.outbound.picker-reports.index', 'icon' => 'fa-solid fa-file-lines', 'parent_slug' => 'reports', 'sort_order' => 1],
            ['name' => 'Laporan Scan Out', 'slug' => 'outbound-packer-report', 'route' => 'admin.reports.packer-reports.index', 'icon' => 'fa-solid fa-truck-ramp-box', 'parent_slug' => 'reports', 'sort_order' => 1.2],
            ['name' => 'Laporan Packing (Legacy)', 'slug' => 'outbound-packer-packing-report', 'route' => 'admin.reports.packer-packing-reports.index', 'icon' => 'fa-solid fa-box-archive', 'parent_slug' => 'reports', 'sort_order' => 1.15, 'is_active' => false],
            ['name' => 'Laporan Stok Pengaman', 'slug' => 'report-low-stock', 'route' => 'admin.reports.low-stock.index', 'icon' => 'fa-solid fa-triangle-exclamation', 'parent_slug' => 'reports', 'sort_order' => 1.25],
            ['name' => 'Laporan Stok', 'slug' => 'report-stock', 'route' => 'admin.reports.stock.index', 'icon' => 'fa-solid fa-boxes-stacked', 'parent_slug' => 'reports', 'sort_order' => 1.27],
            ['name' => 'Laporan Stok Per Tanggal', 'slug' => 'report-stock-as-of-date', 'route' => 'admin.reports.stock-as-of-date.index', 'icon' => 'fa-solid fa-calendar-day', 'parent_slug' => 'reports', 'sort_order' => 1.28],
            ['name' => 'Analitik Transfer Gudang', 'slug' => 'report-transfer-analytics', 'route' => 'admin.reports.transfer-analytics.index', 'icon' => 'fa-solid fa-arrow-right-arrow-left', 'parent_slug' => 'reports', 'sort_order' => 1.3],
            ['name' => 'Perencanaan Pengadaan Stok', 'slug' => 'report-stock-planning', 'route' => 'admin.reports.stock-planning.index', 'icon' => 'fa-solid fa-chart-line', 'parent_slug' => 'reports', 'sort_order' => 1.35],
            ['name' => 'Aktivitas User', 'slug' => 'activity-logs', 'route' => 'admin.reports.activity-logs.index', 'icon' => 'fa-solid fa-clipboard-check', 'parent_slug' => 'reports', 'sort_order' => 2],
            ['name' => 'Laporan Stock Opname', 'slug' => 'report-stock-opname', 'route' => 'admin.reports.stock-opname.index', 'icon' => 'fa-solid fa-clipboard-list', 'parent_slug' => 'reports', 'sort_order' => 3],
        ];

        foreach ($menuRows as $menu) {
            if ($menu['parent_slug'] === null) {
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'name' => $menu['name'],
                        'route' => $menu['route'],
                        'icon' => $menu['icon'],
                        'parent_id' => null,
                        'sort_order' => $menu['sort_order'],
                        'is_active' => $menu['is_active'] ?? true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        foreach ($menuRows as $menu) {
            if ($menu['parent_slug'] !== null) {
                $parent = DB::table('menus')->where('slug', $menu['parent_slug'])->first();
                DB::table('menus')->updateOrInsert(
                    ['slug' => $menu['slug']],
                    [
                        'name' => $menu['name'],
                        'route' => $menu['route'],
                        'icon' => $menu['icon'],
                        'parent_id' => $parent?->id,
                        'sort_order' => $menu['sort_order'],
                        'is_active' => $menu['is_active'] ?? true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $this->grantAllPermissions('superadmin');

        $permissionSets = [
            'user' => [
                'view' => ['dashboard', 'item-stocks'],
            ],
            'picker' => [
                'view' => ['outbound-qc-scan'],
            ],
            'packer' => [
                'view' => ['outbound-scan-out', 'outbound-packer-scan-outs'],
            ],
            'admin-scan' => [
                'view' => ['outbound-scan-out', 'outbound-packer-scan-outs'],
            ],
            'captain' => [
                'view' => [
                    'dashboard',
                    'item-stocks',
                    'stock-mutations',
                    'stock-opname',
                    'stock-adjustments',
                    'damaged-goods',
                    'damaged-allocations',                    
                    'picker-transit',
                    'picking-list',
                    'receipts',
                    'inbound-return',
                    'outbound-manual',
                    'outbound-return',
                    'outbound-qc-scan',
                    'outbound-scan-out',
                    'outbound-qc-scan-history',
                    'outbound-packer-scan-outs',
                    'outbound-picker-report',
                    'outbound-packer-report',
                    'report-low-stock',
                    'report-stock',
                    'report-stock-as-of-date',
                    'report-transfer-analytics',
                    'report-stock-planning',
                    'report-stock-opname',
                ],
            ],
            'admin-resi' => [
                'view' => [
                    'dashboard',
                    'item-stocks',
                    'stock-mutations',
                    'resi-import',
                    'picker-transit',
                    'picking-list',
                    'outbound-qc-scan-history',
                    'outbound-packer-scan-outs',
                ],
                'operate' => ['resi-import'],
            ],
            'admin-retur' => [
                'view' => [
                    'dashboard',
                    'item-stocks',
                    'stock-mutations',
                    'report-low-stock',
                    'report-stock',
                    'report-stock-as-of-date',
                ],
                'operate' => [
                    'inbound-return',
                    'outbound-return',
                    'damaged-goods',
                    'damaged-allocations',
                ],
            ],
            'admin-gudang' => [
                'view' => [
                    'dashboard',
                    'users',
                    'roles',
                    'item-stocks',
                    'stock-mutations',
                    'picker-transit',
                    'picking-list',
                    'outbound-qc-scan-history',
                    'outbound-packer-scan-outs',
                    'outbound-picker-report',
                    'outbound-packer-report',
                    'report-low-stock',
                    'report-stock',
                    'report-stock-as-of-date',
                    'report-transfer-analytics',
                    'report-stock-planning',
                    'activity-logs',
                    'report-stock-opname',
                ],
                'operate' => [
                    'divisi',
                    'kurir',
                    'categories',
                    'items',
                    'warehouses',
                    'stores',
                    'stock-opname',
                    'stock-adjustments',
                    'stock-transfers',
                    'damaged-goods',
                    'damaged-allocations',
                    'resi-import',
                    'inbound-receiving',
                    'inbound-return',
                    'outbound-manual',
                    'outbound-return',
                    'outbound-packer-scan-exceptions',
                ],
            ],
            'kepala-gudang' => [
                'view' => [
                    'dashboard',
                    'users',
                    'roles',
                    'divisi',
                    'kurir',
                    'categories',
                    'items',
                    'warehouses',
                    'stores',
                    'item-stocks',
                    'stock-mutations',
                    'picker-transit',
                    'picking-list',
                    'outbound-qc-scan-history',
                    'outbound-packer-scan-outs',
                    'outbound-picker-report',
                    'outbound-packer-report',
                    'report-low-stock',
                    'report-stock',
                    'report-stock-as-of-date',
                    'report-transfer-analytics',
                    'report-stock-planning',
                    'activity-logs',
                    'report-stock-opname',
                ],
                'full' => [
                    'stock-opname',
                    'stock-adjustments',
                    'stock-transfers',
                    'damaged-goods',
                    'damaged-allocations',
                    'resi-import',
                    'inbound-receiving',
                    'inbound-return',
                    'outbound-manual',
                    'outbound-return',
                    'outbound-packer-scan-exceptions',
                ],
            ],
        ];

        foreach ($permissionSets as $roleSlug => $sets) {
            $this->grantRolePermissions($roleSlug, $sets);
        }
    }

    private function grantAllPermissions(string $roleSlug): void
    {
        $role = DB::table('roles')->where('slug', $roleSlug)->first();
        if (!$role) {
            return;
        }

        DB::table('permission_menu')->where('role_id', $role->id)->delete();

        foreach (DB::table('menus')->get() as $menu) {
            $this->grantMenuPermission((int) $role->id, (int) $menu->id, true, true, true, true);
        }
    }

    /**
     * @param array<string,array<int,string>> $sets
     */
    private function grantRolePermissions(string $roleSlug, array $sets): void
    {
        $role = DB::table('roles')->where('slug', $roleSlug)->first();
        if (!$role) {
            return;
        }

        DB::table('permission_menu')->where('role_id', $role->id)->delete();

        $this->grantMenus((int) $role->id, $sets['view'] ?? [], true, false, false, false);
        $this->grantMenus((int) $role->id, $sets['operate'] ?? [], true, true, true, false);
        $this->grantMenus((int) $role->id, $sets['full'] ?? [], true, true, true, true);
    }

    /**
     * @param array<int,string> $menuSlugs
     */
    private function grantMenus(int $roleId, array $menuSlugs, bool $canView, bool $canCreate, bool $canUpdate, bool $canDelete): void
    {
        if (empty($menuSlugs)) {
            return;
        }

        $menus = DB::table('menus')->whereIn('slug', $menuSlugs)->get(['id']);
        foreach ($menus as $menu) {
            $this->grantMenuPermission($roleId, (int) $menu->id, $canView, $canCreate, $canUpdate, $canDelete);
        }
    }

    private function grantMenuPermission(
        int $roleId,
        int $menuId,
        bool $canView,
        bool $canCreate,
        bool $canUpdate,
        bool $canDelete
    ): void {
        DB::table('permission_menu')->updateOrInsert(
            ['role_id' => $roleId, 'menu_id' => $menuId],
            [
                'can_view' => $canView,
                'can_create' => $canCreate,
                'can_update' => $canUpdate,
                'can_delete' => $canDelete,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
