<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogMessage
{
    public function snapshot(Request $request): array
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        $config = $this->resourceConfig($routeName);
        if (!$config) {
            return $this->boundModelSnapshot($request);
        }

        $id = $this->routeId($request, $config['id_param'] ?? 'id');
        if (!$id || empty($config['table']) || !Schema::hasTable($config['table'])) {
            return $this->boundModelSnapshot($request);
        }

        $row = DB::table($config['table'])->where('id', $id)->first();
        if (!$row) {
            return [];
        }

        return [
            'id' => (int) $row->id,
            'code' => $this->firstFilled($row, ['code', 'sku', 'name', 'no_resi', 'id_pesanan', 'email']),
            'name' => $this->firstFilled($row, ['name', 'sku', 'email', 'code']),
            'item_count' => $this->itemCount($config, (int) $row->id),
            'qty_total' => $this->qtyTotal($config, (int) $row->id),
        ];
    }

    public function build(Request $request, Response $response, ?Authenticatable $user, array $snapshot, array $payload): string
    {
        $actor = $user?->name ?: 'User';
        $routeName = (string) ($request->route()?->getName() ?? '');
        $method = strtoupper($request->method());
        $ok = $response->getStatusCode() < 400;
        $responseData = $this->responseData($response);

        $description = $this->specialDescription($routeName, $method, $payload, $responseData, $snapshot)
            ?? $this->resourceDescription($request, $routeName, $method, $payload, $responseData, $snapshot)
            ?? $this->genericDescription($routeName, $method, $payload);

        if (!$ok) {
            $description = 'gagal '.$description;
        }

        return substr(trim($actor.' '.$description), 0, 191);
    }

    private function resourceDescription(Request $request, string $routeName, string $method, array $payload, array $responseData, array $snapshot): ?string
    {
        $config = $this->resourceConfig($routeName);
        if (!$config) {
            return null;
        }

        $label = $config['label'];
        $action = $this->actionFromRoute($routeName, $method);
        $entity = $snapshot ?: $this->entityFromResponse($responseData) ?: $this->latestEntity($config, $request);
        $code = $entity['code'] ?? $entity['name'] ?? null;
        $itemCount = $entity['item_count'] ?? $this->payloadItemCount($payload);
        $qtyTotal = $entity['qty_total'] ?? $this->payloadQtyTotal($payload);

        if (str_ends_with($routeName, '.import')) {
            return $this->importDescription($label, $responseData, $payload);
        }

        $sentence = $action.' '.$label;
        if ($code) {
            $sentence .= ' '.$this->identifierLabel($config).' '.$code;
        }
        if ($itemCount > 0) {
            $sentence .= ' sebanyak '.$itemCount.' item';
        }
        if ($qtyTotal > 0 && $qtyTotal !== $itemCount) {
            $sentence .= ' dengan total qty '.$qtyTotal;
        }

        return $sentence;
    }

    private function specialDescription(string $routeName, string $method, array $payload, array $responseData, array $snapshot): ?string
    {
        return match ($routeName) {
            'login' => 'login ke aplikasi',
            'logout' => 'logout dari aplikasi',
            'register' => 'mendaftarkan akun baru'.($payload['email'] ?? null ? ' dengan email '.$payload['email'] : ''),
            'password.email' => 'meminta link reset password'.($payload['email'] ?? null ? ' untuk '.$payload['email'] : ''),
            'password.store' => 'mengatur ulang password',
            'password.update' => 'mengubah password akun',
            'profile.update' => 'memperbarui profil akun',
            'profile.destroy' => 'menghapus akun sendiri',
            'verification.send' => 'mengirim ulang email verifikasi',
            'admin.masterdata.permissions.update' => 'memperbarui permission role '.$this->entityText($snapshot, $payload['role'] ?? null),
            'picker.start' => 'memulai sesi picker',
            'picker.items.store' => 'menambahkan SKU '.$this->value($payload, 'sku', 'item_id').' ke sesi picker sebanyak '.max(1, (int) ($payload['qty'] ?? 1)).' item',
            'picker.items.update' => 'memperbarui item sesi picker menjadi qty '.(int) ($payload['qty'] ?? 0),
            'picker.items.destroy' => 'menghapus item dari sesi picker',
            'picker.scan-item' => 'melakukan scan picker SKU '.$this->value($payload, 'code', 'sku').' sebanyak '.max(1, (int) ($payload['qty'] ?? 1)).' item',
            'picker.submit' => 'menyelesaikan sesi picker',
            'picker.scan-out.scan' => 'melakukan scan out resi '.$this->value($payload, 'code', 'resi', 'no_resi'),
            'qc.start' => 'membuka sesi QC scan',
            'qc.resi-record' => 'mencatat resi '.$this->value($payload, 'code', 'resi', 'no_resi', 'resi_id').' untuk QC',
            'qc.scan-item' => 'melakukan scan QC SKU '.$this->value($payload, 'code', 'sku').' sebanyak '.max(1, (int) ($payload['qty'] ?? 1)).' item',
            'opname.batch.create' => 'mencoba membuat batch stock opname mobile',
            'opname.batch.complete' => 'menyelesaikan batch stock opname '.$this->value($payload, 'code'),
            'opname.items.store' => 'menambahkan item ke stock opname mobile sebanyak '.$this->payloadItemCount($payload).' item',
            'opname.items.update' => 'memperbarui hasil hitung stock opname mobile',
            'opname.items.destroy' => 'menghapus item dari stock opname mobile',
            'admin.inventory.resi-import.import' => 'mengimport resi sebanyak '.(int) ($responseData['resis'] ?? 0).' resi dan '.(int) ($responseData['details'] ?? 0).' SKU',
            'admin.inventory.resi-import.cancel' => 'membatalkan resi '.$this->value($payload, 'no_resi', 'id_pesanan', 'resi_id'),
            'admin.inventory.resi-import.uncancel' => 'mengaktifkan kembali resi '.$this->value($payload, 'no_resi', 'id_pesanan', 'resi_id'),
            'admin.inventory.picking-list.store-qty' => 'menambahkan picking list SKU '.$this->value($payload, 'sku').' sebanyak '.(int) ($payload['qty'] ?? 0).' item',
            'admin.inventory.picking-list.recalculate' => 'menghitung ulang picking list',
            'admin.inventory.picking-list.exception-return' => 'mengembalikan picking exception SKU '.$this->value($payload, 'sku').' sebanyak '.(int) ($payload['qty'] ?? 0).' item',
            'admin.inventory.picker-transit.recalculate-today' => 'menghitung ulang transit picker hari ini',
            default => null,
        };
    }

    private function importDescription(string $label, array $responseData, array $payload): string
    {
        $transactions = (int) ($responseData['transactions'] ?? $responseData['resis'] ?? 0);
        $items = (int) ($responseData['items'] ?? $responseData['details'] ?? $this->payloadItemCount($payload));

        $sentence = 'mengimport '.$label;
        if ($transactions > 0) {
            $sentence .= ' sebanyak '.$transactions.' transaksi';
        }
        if ($items > 0) {
            $sentence .= ($transactions > 0 ? ' dan ' : ' sebanyak ').$items.' item';
        }

        return $sentence;
    }

    private function genericDescription(string $routeName, string $method, array $payload): string
    {
        $action = match ($method) {
            'POST' => 'memproses',
            'PUT', 'PATCH' => 'memperbarui',
            'DELETE' => 'menghapus',
            default => 'mengakses',
        };

        $target = $routeName !== '' ? $this->humanizeRoute($routeName) : 'data aplikasi';
        $count = $this->payloadItemCount($payload);

        return $action.' '.$target.($count > 0 ? ' sebanyak '.$count.' item' : '');
    }

    public function buildContext(Request $request, array $payload, array $snapshot): ?array
    {
        $items = $this->resolveItemsFromPayload($payload);

        if (empty($items) && !empty($snapshot['id'])) {
            $routeName = (string) ($request->route()?->getName() ?? '');
            $items = $this->resolveItemsFromDb($routeName, (int) $snapshot['id']);
        }

        return !empty($items) ? ['items' => $items] : null;
    }

    private function resolveItemsFromPayload(array $payload): array
    {
        if (empty($payload['items']) || !is_array($payload['items'])) {
            return [];
        }

        $itemIds = collect($payload['items'])
            ->pluck('item_id')->filter()->map('intval')->unique()->values()->all();

        if (empty($itemIds)) {
            return [];
        }

        $details = DB::table('items')
            ->whereIn('id', $itemIds)
            ->select('id', 'sku', 'name')
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($payload['items'] as $row) {
            $id = (int) ($row['item_id'] ?? 0);
            if ($id && $details->has($id)) {
                $d = $details->get($id);
                $items[] = [
                    'sku'  => $d->sku,
                    'name' => $d->name,
                    'qty'  => (int) ($row['qty'] ?? $row['counted_qty'] ?? 1),
                ];
            }
        }

        return $items;
    }

    private function resolveItemsFromDb(string $routeName, int $resourceId): array
    {
        $config = $this->resourceConfig($routeName);

        if (
            empty($config['item_table']) ||
            empty($config['item_fk']) ||
            !Schema::hasTable($config['item_table'])
        ) {
            return [];
        }

        $qtyCol = $config['qty_column'] ?? 'qty';

        $rows = DB::table($config['item_table'])
            ->where($config['item_fk'], $resourceId)
            ->get(['item_id', $qtyCol]);

        if ($rows->isEmpty()) {
            return [];
        }

        $itemIds = $rows->pluck('item_id')->filter()->map('intval')->unique()->values()->all();
        if (empty($itemIds)) {
            return [];
        }

        $details = DB::table('items')
            ->whereIn('id', $itemIds)
            ->select('id', 'sku', 'name')
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row->item_id ?? 0);
            if ($id && $details->has($id)) {
                $d = $details->get($id);
                $items[] = [
                    'sku'  => $d->sku,
                    'name' => $d->name,
                    'qty'  => (int) ($row->{$qtyCol} ?? 1),
                ];
            }
        }

        return $items;
    }

    public function resourceConfig(string $routeName): ?array
    {
        $resources = [
            'admin.inbound.receipts' => ['label' => 'penerimaan barang', 'table' => 'inbound_transactions', 'type' => 'receipt', 'item_table' => 'inbound_items', 'item_fk' => 'inbound_transaction_id'],
            'admin.inbound.returns' => ['label' => 'retur inbound', 'table' => 'inbound_transactions', 'type' => 'return', 'item_table' => 'inbound_items', 'item_fk' => 'inbound_transaction_id'],
            'admin.outbound.pickers' => ['label' => 'outbound QC scan', 'table' => 'outbound_transactions', 'type' => 'picker', 'item_table' => 'outbound_items', 'item_fk' => 'outbound_transaction_id'],
            'admin.outbound.manuals' => ['label' => 'outbound manual', 'table' => 'outbound_transactions', 'type' => 'manual', 'item_table' => 'outbound_items', 'item_fk' => 'outbound_transaction_id'],
            'admin.outbound.returns' => ['label' => 'retur outbound', 'table' => 'outbound_transactions', 'type' => 'return', 'item_table' => 'outbound_items', 'item_fk' => 'outbound_transaction_id'],
            'admin.inventory.stock-adjustments' => ['label' => 'penyesuaian stok', 'table' => 'stock_adjustments', 'item_table' => 'stock_adjustment_items', 'item_fk' => 'stock_adjustment_id'],
            'admin.inventory.stock-opname' => ['label' => 'stock opname', 'table' => 'stock_opnames', 'item_table' => 'stock_opname_items', 'item_fk' => 'stock_opname_id', 'qty_column' => 'counted_qty'],
            'admin.inventory.damaged-goods' => ['label' => 'barang rusak', 'table' => 'damaged_goods', 'item_table' => 'damaged_good_items', 'item_fk' => 'damaged_good_id'],
            'admin.inventory.damaged-allocations' => ['label' => 'alokasi barang rusak', 'table' => 'damaged_allocations', 'item_table' => 'damaged_allocation_items', 'item_fk' => 'damaged_allocation_id'],
            'admin.masterdata.users' => ['label' => 'user', 'table' => 'users'],
            'admin.masterdata.roles' => ['label' => 'role', 'table' => 'roles'],
            'admin.masterdata.divisi' => ['label' => 'divisi', 'table' => 'divisis'],
            'admin.masterdata.kurir' => ['label' => 'kurir', 'table' => 'kurirs'],
            'admin.masterdata.categories' => ['label' => 'kategori', 'table' => 'categories'],
            'admin.masterdata.items' => ['label' => 'master item', 'table' => 'items'],
            'admin.masterdata.stores' => ['label' => 'store', 'table' => 'stores'],
            'admin.masterdata.menus' => ['label' => 'menu', 'table' => 'menus'],
            'admin.outbound.packer-scan-exceptions' => ['label' => 'SKU exception packer', 'table' => 'packer_scan_exceptions', 'id_param' => 'exception'],
            'admin.outbound.qc-scan-history' => ['label' => 'history QC scan', 'table' => 'qc_scan_resis'],
        ];

        foreach ($resources as $prefix => $config) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) {
                return $config;
            }
        }

        return null;
    }

    private function actionFromRoute(string $routeName, string $method): string
    {
        if (str_ends_with($routeName, '.approve')) {
            return 'menyetujui';
        }
        if (str_ends_with($routeName, '.destroy') || $method === 'DELETE') {
            return 'menghapus';
        }
        if (str_ends_with($routeName, '.update') || in_array($method, ['PUT', 'PATCH'], true)) {
            return 'memperbarui';
        }
        if (str_ends_with($routeName, '.store')) {
            return 'membuat';
        }

        return 'memproses';
    }

    private function latestEntity(array $config, Request $request): array
    {
        if (empty($config['table']) || !Schema::hasTable($config['table'])) {
            return [];
        }

        $query = DB::table($config['table'])->orderByDesc('id');
        if (!empty($config['type']) && Schema::hasColumn($config['table'], 'type')) {
            $query->where('type', $config['type']);
        }
        if ($request->user() && Schema::hasColumn($config['table'], 'created_by')) {
            $query->where('created_by', $request->user()->id);
        }

        $row = $query->first();
        if (!$row) {
            return [];
        }

        return [
            'id' => (int) $row->id,
            'code' => $this->firstFilled($row, ['code', 'sku', 'name', 'email']),
            'name' => $this->firstFilled($row, ['name', 'sku', 'email', 'code']),
            'item_count' => $this->itemCount($config, (int) $row->id),
            'qty_total' => $this->qtyTotal($config, (int) $row->id),
        ];
    }

    private function entityFromResponse(array $responseData): array
    {
        foreach (['category', 'item', 'user', 'role', 'menu', 'store', 'exception'] as $key) {
            if (!empty($responseData[$key]) && is_array($responseData[$key])) {
                return [
                    'id' => (int) ($responseData[$key]['id'] ?? 0),
                    'code' => $responseData[$key]['code'] ?? $responseData[$key]['sku'] ?? $responseData[$key]['name'] ?? $responseData[$key]['email'] ?? null,
                    'name' => $responseData[$key]['name'] ?? $responseData[$key]['sku'] ?? $responseData[$key]['email'] ?? null,
                ];
            }
        }

        return [];
    }

    private function itemCount(array $config, int $id): int
    {
        if (empty($config['item_table']) || empty($config['item_fk']) || !Schema::hasTable($config['item_table'])) {
            return 0;
        }

        return (int) DB::table($config['item_table'])->where($config['item_fk'], $id)->count();
    }

    private function qtyTotal(array $config, int $id): int
    {
        if (empty($config['item_table']) || empty($config['item_fk']) || !Schema::hasTable($config['item_table'])) {
            return 0;
        }

        $qtyColumn = $config['qty_column'] ?? 'qty';
        if (!Schema::hasColumn($config['item_table'], $qtyColumn)) {
            return 0;
        }

        return (int) DB::table($config['item_table'])->where($config['item_fk'], $id)->sum($qtyColumn);
    }

    private function routeId(Request $request, string $param): ?int
    {
        $value = $request->route($param) ?? $request->route('id');
        if ($value instanceof Model) {
            return (int) $value->getKey();
        }
        if (is_object($value) && isset($value->id)) {
            return (int) $value->id;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function boundModelSnapshot(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof Model) {
                return [
                    'id' => (int) $value->getKey(),
                    'code' => $value->getAttribute('code') ?: $value->getAttribute('sku') ?: $value->getAttribute('name') ?: $value->getAttribute('email'),
                    'name' => $value->getAttribute('name') ?: $value->getAttribute('sku') ?: $value->getAttribute('email'),
                ];
            }
        }

        return [];
    }

    private function responseData(Response $response): array
    {
        $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
        if ($content === '' || !str_starts_with(ltrim($content), '{')) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function payloadItemCount(array $payload): int
    {
        if (!empty($payload['items']) && is_array($payload['items'])) {
            return count($payload['items']);
        }

        return !empty($payload['item_id']) || !empty($payload['sku']) || !empty($payload['code']) ? 1 : 0;
    }

    private function payloadQtyTotal(array $payload): int
    {
        if (!empty($payload['items']) && is_array($payload['items'])) {
            return (int) collect($payload['items'])->sum(fn ($row) => (int) ($row['qty'] ?? $row['counted_qty'] ?? 0));
        }

        return (int) ($payload['qty'] ?? $payload['counted_qty'] ?? 0);
    }

    private function firstFilled(object $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                return (string) $row->{$column};
            }
        }

        return null;
    }

    private function identifierLabel(array $config): string
    {
        return in_array($config['table'] ?? '', ['items', 'packer_scan_exceptions'], true) ? 'SKU' : 'kode';
    }

    private function entityText(array $snapshot, mixed $fallback = null): string
    {
        return (string) ($snapshot['code'] ?? $snapshot['name'] ?? $fallback ?? '-');
    }

    private function value(array $payload, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
                return (string) $payload[$key];
            }
        }

        return '-';
    }

    private function humanizeRoute(string $routeName): string
    {
        $parts = explode('.', $routeName);
        $last = end($parts) ?: $routeName;
        $target = str_replace(['admin.', '.', '-'], ['', ' ', ' '], $routeName);

        return trim(str_replace($last, '', $target)) ?: 'data aplikasi';
    }
}
