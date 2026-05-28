@extends('layouts.admin')

@section('title', 'Atur Permission - ' . $role->name)
@section('page_title', 'Atur Permission: ' . $role->name)

@section('page_breadcrumbs')
    <span class="text-muted">Home</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Masterdata</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Permissions</span>
    <span class="mx-2">-</span>
    <span class="text-dark">{{ $role->name }}</span>
@endsection

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        <div class="card">
            <div class="card-body py-6">
                <form method="POST" action="{{ route('admin.masterdata.permissions.update', $role->id) }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>Menu</th>
                                    <th class="text-center">Lihat</th>
                                    <th class="text-center">Tambah</th>
                                    <th class="text-center">Ubah</th>
                                    <th class="text-center">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($menus as $parent)
                                    @php
                                        $p = $permissions->get($parent->id);
                                    @endphp
                                    <tr class="fw-bold">
                                        <td>{{ $parent->name }}</td>
                                        <td class="text-center"><input type="checkbox" name="can_view[{{ $parent->id }}]" @checked($p?->can_view)></td>
                                        <td class="text-center"><input type="checkbox" name="can_create[{{ $parent->id }}]" @checked($p?->can_create)></td>
                                        <td class="text-center"><input type="checkbox" name="can_update[{{ $parent->id }}]" @checked($p?->can_update)></td>
                                        <td class="text-center"><input type="checkbox" name="can_delete[{{ $parent->id }}]" @checked($p?->can_delete)></td>
                                    </tr>
                                    @foreach($parent->children()->orderBy('sort_order')->orderBy('name')->get() as $child)
                                        @php $c = $permissions->get($child->id); @endphp
                                        <tr>
                                            <td class="ps-10">- {{ $child->name }}</td>
                                            <td class="text-center"><input type="checkbox" name="can_view[{{ $child->id }}]" @checked($c?->can_view)></td>
                                            <td class="text-center"><input type="checkbox" name="can_create[{{ $child->id }}]" @checked($c?->can_create)></td>
                                            <td class="text-center"><input type="checkbox" name="can_update[{{ $child->id }}]" @checked($c?->can_update)></td>
                                            <td class="text-center"><input type="checkbox" name="can_delete[{{ $child->id }}]" @checked($c?->can_delete)></td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('admin.masterdata.permissions.index') }}" class="btn btn-light me-3">Kembali</a>
                        <button type="submit" class="btn btn-primary">Simpan Permission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@include('layouts.partials.form-submit-confirmation')
