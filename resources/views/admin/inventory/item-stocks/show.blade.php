@extends('layouts.admin')

@section('title', 'Detail Stok: ' . $item->sku)
@section('page_title', 'Detail Stok')

@section('content')
<div class="row g-5 g-xl-8 mb-6">
    <div class="col-xl-4">
        <div class="card card-flush h-100">
            <div class="card-header pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bolder fs-3 mb-1">Info Item</span>
                </h3>
                <div class="card-toolbar">
                    <a href="{{ route('admin.inventory.item-stocks.index') }}" class="btn btn-sm btn-light">Kembali</a>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="d-flex align-items-center mb-5">
                    <div class="symbol symbol-50px me-4 bg-light-primary rounded">
                        <span class="symbol-label fs-3 fw-bolder text-primary">{{ strtoupper(substr($item->sku, 0, 2)) }}</span>
                    </div>
                    <div>
                        <div class="fw-bolder fs-5 text-gray-800">{{ $item->name }}</div>
                        <div class="text-muted fs-7">{{ $item->sku }}</div>
                    </div>
                </div>

                <div class="separator separator-dashed my-5"></div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="fs-7 fw-bold text-gray-500 mb-1">Kategori</div>
                        <div class="fs-6">{{ $item->category?->name ?? '-' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="fs-7 fw-bold text-gray-500 mb-1">Tipe</div>
                        <div class="fs-6">
                            @if($isBundle)
                                <span class="badge badge-light-primary">Bundle</span>
                            @else
                                <span class="badge badge-light-secondary">Regular</span>
                            @endif
                        </div>
                    </div>
                    @if($item->address)
                    <div class="col-12">
                        <div class="fs-7 fw-bold text-gray-500 mb-1">Alamat/Lokasi</div>
                        <div class="fs-6">{{ $item->address }}</div>
                    </div>
                    @endif
                    @if($item->description)
                    <div class="col-12">
                        <div class="fs-7 fw-bold text-gray-500 mb-1">Deskripsi</div>
                        <div class="fs-6 text-muted">{{ $item->description }}</div>
                    </div>
                    @endif
                    @if($item->safety_stock)
                    <div class="col-6">
                        <div class="fs-7 fw-bold text-gray-500 mb-1">Safety Stock</div>
                        <div class="fs-6">{{ $item->safety_stock }}</div>
                    </div>
                    @endif
                </div>

                <div class="separator separator-dashed my-5"></div>

                <div class="text-center">
                    <div class="fs-7 fw-bold text-gray-500 mb-2">Stok Saat Ini</div>
                    <div class="fs-2x fw-bolder {{ $currentStock <= ($item->safety_stock ?? 0) && $item->safety_stock ? 'text-danger' : 'text-success' }}">
                        {{ number_format($currentStock) }}
                    </div>
                    @if($isBundle)
                        <div class="text-muted fs-8 mt-1">virtual (bundle)</div>
                    @endif
                    @if($item->safety_stock && $currentStock <= $item->safety_stock)
                        <div class="badge badge-light-danger mt-2">Di bawah safety stock ({{ $item->safety_stock }})</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card card-flush h-100">
            <div class="card-header pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bolder fs-3 mb-1">Ringkasan Mutasi</span>
                </h3>
            </div>
            <div class="card-body pt-4">
                @php
                    $totalIn = $mutations->getCollection()->where('direction', 'in')->sum('qty');
                    $totalOut = $mutations->getCollection()->where('direction', 'out')->sum('qty');
                @endphp
                <div class="row g-4">
                    <div class="col-6">
                        <div class="border border-success rounded p-4 text-center">
                            <div class="fs-7 fw-bold text-success mb-1">Total Masuk (halaman ini)</div>
                            <div class="fs-2x fw-bolder text-success">+{{ number_format($totalIn) }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border border-danger rounded p-4 text-center">
                            <div class="fs-7 fw-bold text-danger mb-1">Total Keluar (halaman ini)</div>
                            <div class="fs-2x fw-bolder text-danger">-{{ number_format($totalOut) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <span class="card-label fw-bolder fs-3">History Mutasi Stok</span>
        </div>
        <div class="card-toolbar">
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}" />
                <select name="per_page" class="form-select form-select-solid form-select-sm w-auto" onchange="this.form.submit()">
                    @foreach([10, 20, 50, 100] as $n)
                        <option value="{{ $n }}" {{ request('per_page', 20) == $n ? 'selected' : '' }}>{{ $n }} per halaman</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
    <div class="card-body py-4">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Tanggal</th>
                        <th>Arah</th>
                        <th class="text-end">Qty</th>
                        <th>Sumber</th>
                        <th>Kode Sumber</th>
                        <th>Catatan</th>
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mutations as $mut)
                    <tr>
                        <td class="text-nowrap">{{ $mut->occurred_at?->format('Y-m-d H:i') ?? $mut->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($mut->direction === 'in')
                                <span class="badge badge-light-success">
                                    <i class="fas fa-arrow-down text-success me-1"></i> Masuk
                                </span>
                            @else
                                <span class="badge badge-light-danger">
                                    <i class="fas fa-arrow-up text-danger me-1"></i> Keluar
                                </span>
                            @endif
                        </td>
                        <td class="text-end fw-bold {{ $mut->direction === 'in' ? 'text-success' : 'text-danger' }}">
                            {{ $mut->direction === 'in' ? '+' : '-' }}{{ number_format($mut->qty) }}
                        </td>
                        <td>
                            @php
                                $sourceLabel = match($mut->source_type) {
                                    'inbound' => 'Inbound',
                                    'outbound' => 'Outbound',
                                    'adjustment' => 'Penyesuaian',
                                    'opname' => 'Stock Opname',
                                    'damaged' => 'Barang Rusak',
                                    default => ucfirst($mut->source_type ?? '-'),
                                };
                                $subtypeLabel = match($mut->source_subtype) {
                                    'receipt' => 'Penerimaan',
                                    'return' => 'Retur',
                                    'manual' => 'Manual',
                                    'picker' => 'QC Scan',
                                    default => $mut->source_subtype ? ucfirst($mut->source_subtype) : null,
                                };
                            @endphp
                            <span>{{ $sourceLabel }}</span>
                            @if($subtypeLabel)
                                <span class="text-muted fs-8"> / {{ $subtypeLabel }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if($mut->source_code)
                                <span class="badge badge-light fw-semibold">{{ $mut->source_code }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $mut->note ?: '-' }}</td>
                        <td class="text-muted">{{ $mut->creator?->name ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-8">Belum ada riwayat mutasi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($mutations->hasPages())
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
            <div class="text-muted fs-7">
                Menampilkan {{ $mutations->firstItem() }}–{{ $mutations->lastItem() }} dari {{ $mutations->total() }} mutasi
            </div>
            <div>
                {{ $mutations->links('pagination::bootstrap-5') }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
