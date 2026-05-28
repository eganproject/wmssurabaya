@extends('layouts.admin')

@section('title', 'Edit Menu')
@section('page_title', 'Edit Menu')

@section('page_breadcrumbs')
    <span class="text-muted">Home</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Masterdata</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Menus</span>
    <span class="mx-2">-</span>
    <span class="text-dark">Edit</span>
@endsection

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        <div class="card">
            <div class="card-body py-6">
                <form method="POST" action="{{ route('admin.masterdata.menus.update', $menu->id) }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="mb-10">
                        <label class="form-label required">Nama</label>
                        <input type="text" name="name" value="{{ old('name', $menu->name) }}" class="form-control @error('name') is-invalid @enderror form-control-solid" required />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label required">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug', $menu->slug) }}" class="form-control @error('slug') is-invalid @enderror form-control-solid" required />
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Route</label>
                        <input type="text" name="route" value="{{ old('route', $menu->route) }}" class="form-control @error('route') is-invalid @enderror form-control-solid" />
                        @error('route')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Icon</label>
                        <input type="text" name="icon" value="{{ old('icon', $menu->icon) }}" class="form-control @error('icon') is-invalid @enderror form-control-solid" />
                        @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Parent</label>
                        <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror form-select-solid">
                            <option value="">(Tidak ada)</option>
                            @foreach($parents as $p)
                                <option value="{{ $p->id }}" @selected(old('parent_id', $menu->parent_id) == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Urutan</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $menu->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror form-control-solid" />
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check form-switch mb-10">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $menu->is_active))>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('admin.masterdata.menus.index') }}" class="btn btn-light me-3">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@include('layouts.partials.form-submit-confirmation')
