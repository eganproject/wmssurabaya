@extends('layouts.admin')

@section('title', 'Detail Stock Mutation')
@section('page_title', 'Detail Stock Mutation')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <div class="fw-bolder fs-5">Mutation #{{ $mutation->id }}</div>
                <div class="text-muted fs-7">{{ $mutation->occurred_at?->format('Y-m-d H:i') }}</div>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row mb-6">
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Item</div>
                <div>{{ $mutation->item?->sku }} - {{ $mutation->item?->name }}</div>
            </div>
            <div class="col-md-2">
                <div class="fw-bold text-gray-600">Arah</div>
                <div>{{ strtoupper($mutation->direction) }}</div>
            </div>
            <div class="col-md-2">
                <div class="fw-bold text-gray-600">Qty</div>
                <div>{{ $mutation->qty }}</div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Sumber</div>
                <div>{{ strtoupper($mutation->source_type ?? '-') }}{{ $mutation->source_subtype ? ' / '.$mutation->source_subtype : '' }}</div>
            </div>
        </div>
        <div class="row mb-6">
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Kode Sumber</div>
                <div>{{ $mutation->source_code ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold text-gray-600">Catatan</div>
                <div>{{ $mutation->note ?? '-' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="fw-bolder fs-5">Sumber Data</div>
        </div>
    </div>
    <div class="card-body py-6">
        @if($sourceSummary)
            <div class="row mb-6">
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Jenis</div>
                    <div>{{ $sourceSummary['label'] }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Kode</div>
                    <div>{{ $sourceSummary['code'] }}</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Ref</div>
                    <div>{{ $sourceSummary['ref'] }}</div>
                </div>
            </div>
            <div class="row mb-6">
                <div class="col-md-4">
                    <div class="fw-bold text-gray-600">Tanggal</div>
                    <div>{{ $sourceSummary['date'] ? \Illuminate\Support\Carbon::parse($sourceSummary['date'])->format('Y-m-d H:i') : '-' }}</div>
                </div>
                <div class="col-md-8">
                    <div class="fw-bold text-gray-600">Catatan</div>
                    <div>{{ $sourceSummary['note'] }}</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sourceItems as $row)
                            <tr>
                                <td>
                                    {{ $row['label'] ?? '-' }}
                                    @if(!empty($row['meta']))
                                        <div class="text-muted fs-8">{{ $row['meta'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['qty'] ?? '-' }}</td>
                                <td>{{ $row['note'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted">Tidak ada item.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-muted">Data sumber tidak ditemukan.</div>
        @endif
    </div>
</div>
@endsection
