@extends('layouts.admin')

@section('title', 'Akses API Stok')
@section('page_title', 'Akses API Stok')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.stock-api-access.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.stock-api-access.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.stock-api-access.index', 'delete');
@endphp

@section('content')
<div class="card mb-6">
    <div class="card-body">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-shield-halved fs-2 text-primary mt-1"></i>
            <div>
                <div class="fw-bold">Whitelist IP untuk API stok</div>
                <div class="text-muted">API hanya menerima request Bearer token dari IP aktif di bawah. Jika daftar kosong, seluruh request API ditolak.</div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($canCreate)
<div class="card mb-6">
    <div class="card-header"><h3 class="card-title">Tambah IP server pusat</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.masterdata.stock-api-access.store') }}" class="row g-4 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label required">IP Address</label>
                <input name="ip_address" value="{{ old('ip_address') }}" class="form-control @error('ip_address') is-invalid @enderror" placeholder="contoh: 203.0.113.10" required>
                @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label class="form-label">Keterangan</label>
                <input name="label" value="{{ old('label') }}" class="form-control" placeholder="Server pusat / integrasi">
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Tambahkan IP</button></div>
        </form>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><h3 class="card-title">IP yang diizinkan</h3></div>
    <div class="card-body py-4">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed">
                <thead><tr class="text-gray-400 fw-bold text-uppercase"><th>IP Address</th><th>Keterangan</th><th>Status</th><th>Dibuat</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($ips as $ip)
                    <tr>
                        <td class="fw-bold font-monospace">{{ $ip->ip_address }}</td>
                        <td>{{ $ip->label ?: '-' }}</td>
                        <td><span class="badge badge-{{ $ip->is_active ? 'light-success' : 'light-secondary' }}">{{ $ip->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>{{ $ip->created_at?->format('d M Y H:i') }}</td>
                        <td class="text-end">
                            @if($canUpdate)
                            <form method="POST" action="{{ route('admin.masterdata.stock-api-access.update', $ip) }}" class="d-inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="label" value="{{ $ip->label }}">
                                <input type="hidden" name="is_active" value="{{ $ip->is_active ? 0 : 1 }}">
                                <button class="btn btn-sm btn-light">{{ $ip->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                            @endif
                            @if($canDelete)
                            <form method="POST" action="{{ route('admin.masterdata.stock-api-access.destroy', $ip) }}" class="d-inline" onsubmit="return confirm('Hapus IP ini dari whitelist API?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light-danger">Hapus</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-8">Belum ada IP yang diizinkan. API stok belum dapat diakses.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
