@extends('layouts.admin')

@section('title', 'Ubah Jurusan')
@section('page-title', 'Ubah Jurusan: ' . $major->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.majors.index') }}" class="text-decoration-none">Majors</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.majors.update', $major->id) }}" method="POST">
            @csrf
                    @method('PUT')
            <input type="hidden" name="return_school_unit_id" value="{{ request()->query('school_unit_id') }}">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Jurusan</h5>
                    <p class="text-muted small mb-0">A major is only valid inside its owning unit</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="school_unit_id" class="form-label fw-semibold text-secondary">Unit Sekolah</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="school_unit_id" name="school_unit_id" required>
                            <option value="">Pilih unit...</option>
@foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('school_unit_id', $major->school_unit_id) === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program?->name }})</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Nama Jurusan</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $major->name) }}" placeholder="e.g. IPA, IPS, Teknik Komputer dan Jaringan" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold text-secondary">Kode Jurusan</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $major->code) }}" placeholder="e.g. IPA (optional)">
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-1">
                        <div class="col-md-4">
                            <label for="quota" class="form-label fw-semibold text-secondary">Kuota Total</label>
                            <input type="number" min="1" class="form-control @error('quota') is-invalid @enderror" id="quota" name="quota" value="{{ old('quota', $major->quota) }}" placeholder="Tanpa batas">
                            @error('quota')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="quota_putra" class="form-label fw-semibold text-secondary">Kuota Putra</label>
                            <input type="number" min="1" class="form-control @error('quota_putra') is-invalid @enderror" id="quota_putra" name="quota_putra" value="{{ old('quota_putra', $major->quota_putra) }}" placeholder="Tanpa batas">
                            @error('quota_putra')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="quota_putri" class="form-label fw-semibold text-secondary">Kuota Putri</label>
                            <input type="number" min="1" class="form-control @error('quota_putri') is-invalid @enderror" id="quota_putri" name="quota_putri" value="{{ old('quota_putri', $major->quota_putri) }}" placeholder="Tanpa batas">
                            @error('quota_putri')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <p class="form-text mt-0 mb-3">Kuota dihitung per tahun ajaran dari pendaftar yang sudah bernomor registrasi. Kosongkan untuk tanpa batas; kuota putra/putri membatasi masing-masing jenis kelamin.</p>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $major->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.majors.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
