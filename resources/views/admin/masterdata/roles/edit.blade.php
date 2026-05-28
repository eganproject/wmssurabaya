@extends('layouts.admin')

@section('title', 'Edit Role')
@section('page_title', 'Edit Role')

@section('page_breadcrumbs')
    <span class="text-muted">Home</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Masterdata</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Roles</span>
    <span class="mx-2">-</span>
    <span class="text-dark">Edit</span>
@endsection

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        <div class="card">
            <div class="card-body py-6">
                <form method="POST" action="{{ route('admin.masterdata.roles.update', $role->id) }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="mb-10">
                        <label class="form-label required">Nama Role</label>
                        <input type="text" name="name" value="{{ old('name', $role->name) }}" class="form-control @error('name') is-invalid @enderror form-control-solid" required />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label required">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug', $role->slug) }}" class="form-control @error('slug') is-invalid @enderror form-control-solid" required />
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Deskripsi</label>
                        <input type="text" name="description" value="{{ old('description', $role->description) }}" class="form-control @error('description') is-invalid @enderror form-control-solid" />
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('admin.masterdata.roles.index') }}" class="btn btn-light me-3">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@include('layouts.partials.form-submit-confirmation')
