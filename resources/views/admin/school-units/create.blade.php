@extends('layouts.admin')

@section('title', 'Tambah Unit Sekolah')
@section('page-title', 'Tambah Unit Sekolah')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">School Units</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.school-units.store') }}" method="POST">
            @csrf

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Unit Sekolah</h5>
                    <p class="text-muted small mb-0">Define the unit identity used across SPMB</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Nama Unit</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. SMP Reguler" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold text-secondary">Kode Unit</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" placeholder="e.g. 31" required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Tepat 2 digit, unik. Kode ini adalah bagian pertama nomor pendaftaran dan ditentukan oleh panitia unit.</div>
                    </div>
                    <div class="mb-3">
                        <label for="level" class="form-label fw-semibold text-secondary">Jenjang</label>
                        <select class="form-select @error('level') is-invalid @enderror" id="level" name="level" required>
                            <option value="">Pilih jenjang...</option>
                                <option value="PAUD/TK" {{ old('level') == 'PAUD/TK' ? 'selected' : '' }}>PAUD/TK</option>
                                <option value="SD/MI" {{ old('level') == 'SD/MI' ? 'selected' : '' }}>SD/MI</option>
                                <option value="SMP/MTs" {{ old('level') == 'SMP/MTs' ? 'selected' : '' }}>SMP/MTs</option>
                                <option value="SMA" {{ old('level') == 'SMA' ? 'selected' : '' }}>SMA</option>
                                <option value="SMK" {{ old('level') == 'SMK' ? 'selected' : '' }}>SMK</option>
                                <option value="MA" {{ old('level') == 'MA' ? 'selected' : '' }}>MA</option>
                        </select>
                        @error('level')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="program" class="form-label fw-semibold text-secondary">Program</label>
                        <select class="form-select @error('program') is-invalid @enderror" id="program" name="program" required>
                            <option value="">Pilih program...</option>
                                <option value="Reguler" {{ old('program') == 'Reguler' ? 'selected' : '' }}>Reguler</option>
                                <option value="Boarding" {{ old('program') == 'Boarding' ? 'selected' : '' }}>Boarding</option>
                        </select>
                        @error('program')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold text-secondary">Telepon</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="e.g. 0812-3456-7890">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold text-secondary">Alamat</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3" placeholder="Unit address">{{ old('address') }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.school-units.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Simpan <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
