@extends('layouts.admin')

@section('title', 'Tambah Jenis Berkas')
@section('page-title', 'Tambah Jenis Berkas')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.document-types.index') }}" class="text-decoration-none">Document Types</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.document-types.store') }}" method="POST">
            @csrf

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Jenis Berkas</h5>
                    <p class="text-muted small mb-0">Define an uploadable document and its file constraints</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Nama Berkas</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $documentType->name ?? '') }}" placeholder="e.g. Kartu Keluarga" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold text-secondary">Kode</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $documentType->code ?? '') }}" placeholder="e.g. kartu-keluarga" required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Pengenal singkat unik yang dipakai sistem, mis. pas-foto, ijazah-skl.</div>
                    </div>

                    <div class="mb-3">
                        <label for="allowed_extensions" class="form-label fw-semibold text-secondary">Ekstensi yang Diizinkan</label>
                        <input type="text" class="form-control @error('allowed_extensions') is-invalid @enderror" id="allowed_extensions" name="allowed_extensions" value="{{ old('allowed_extensions', $documentType->allowed_extensions ?? '') }}" placeholder="e.g. jpg,jpeg,png,pdf" required>
                        @error('allowed_extensions')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Daftar ekstensi file yang dipisahkan koma, tanpa titik.</div>
                    </div>

                    <div class="mb-3">
                        <label for="max_size_kb" class="form-label fw-semibold text-secondary">Ukuran Maksimum (KB)</label>
                        <input type="number" min="1" class="form-control @error('max_size_kb') is-invalid @enderror" id="max_size_kb" name="max_size_kb" value="{{ old('max_size_kb', $documentType->max_size_kb ?? 2048) }}" required>
                        @error('max_size_kb')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $documentType->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.document-types.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    Simpan <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
