@extends('layouts.admin')

@section('title', 'Edit User')
@section('page_title', 'Edit User')

@section('page_breadcrumbs')
    <span class="text-muted">Home</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Masterdata</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Users</span>
    <span class="mx-2">-</span>
    <span class="text-dark">Edit</span>
@endsection

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        <div class="card">
            <div class="card-body py-6">
                <form method="POST" action="{{ route('admin.masterdata.users.update', $user->id) }}" class="form" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-10">
                        <label class="form-label required">Nama</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror form-control-solid" required />
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label required">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror form-control-solid" required />
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Password (kosongkan bila tidak diubah)</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror form-control-solid" />
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Roles</label>
                        <select name="roles[]" class="form-select @error('roles') is-invalid @enderror form-select-solid" multiple data-control="select2" data-placeholder="Pilih Roles">
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" @selected(collect(old('roles', $user->roles->pluck('id')->all()))->contains($r->id))>{{ $r->name }}</option>
                            @endforeach
                        </select>
                        @error('roles')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Divisi</label>
                        <select name="divisi_id" class="form-select @error('divisi_id') is-invalid @enderror form-select-solid" data-placeholder="Pilih Divisi">
                            <option value="">- Pilih Divisi -</option>
                            @foreach($divisis as $d)
                                <option value="{{ $d->id }}" @selected(old('divisi_id', $user->divisi_id) == $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                        @error('divisi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-10">
                        <label class="form-label">Avatar</label>
                        <div class="d-flex align-items-center gap-4 mb-3">
                            <img src="{{ $user->avatar_url }}" alt="Avatar" class="w-60px h-60px rounded-circle object-cover">
                            <span class="text-muted fs-7">Avatar sekarang</span>
                        </div>
                        <input type="file" name="avatar" class="form-control @error('avatar') is-invalid @enderror form-control-solid" accept=".jpg,.jpeg,.png" />
                        @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Boleh dikosongkan jika tidak ingin mengganti.</div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('admin.masterdata.users.index') }}" class="btn btn-light me-3">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@include('layouts.partials.form-submit-confirmation')
