@extends('layouts.admin')

@section('title', 'Ubah Unit Sekolah')
@section('page-title', 'Ubah Unit Sekolah: ' . $schoolUnit->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">School Units</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.school-units.update', $schoolUnit->id) }}" method="POST">
            @csrf
                    @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Unit Sekolah</h5>
                    <p class="text-muted small mb-0">Define the unit identity used across SPMB</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Nama Unit</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $schoolUnit->name) }}" placeholder="e.g. SMP Reguler" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold text-secondary">Kode Unit</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $schoolUnit->code) }}" placeholder="e.g. 31" required>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Tepat 2 digit, unik. Kode ini adalah bagian pertama nomor pendaftaran dan ditentukan oleh panitia unit.</div>
                    </div>
                    <div class="mb-3">
                        <label for="level" class="form-label fw-semibold text-secondary">Jenjang</label>
                        <select class="form-select @error('level') is-invalid @enderror" id="level" name="level" required>
                            <option value="">Pilih jenjang...</option>
                                <option value="PAUD/TK" {{ old('level', $schoolUnit->level) == 'PAUD/TK' ? 'selected' : '' }}>PAUD/TK</option>
                                <option value="SD/MI" {{ old('level', $schoolUnit->level) == 'SD/MI' ? 'selected' : '' }}>SD/MI</option>
                                <option value="SMP/MTs" {{ old('level', $schoolUnit->level) == 'SMP/MTs' ? 'selected' : '' }}>SMP/MTs</option>
                                <option value="SMA" {{ old('level', $schoolUnit->level) == 'SMA' ? 'selected' : '' }}>SMA</option>
                                <option value="SMK" {{ old('level', $schoolUnit->level) == 'SMK' ? 'selected' : '' }}>SMK</option>
                                <option value="MA" {{ old('level', $schoolUnit->level) == 'MA' ? 'selected' : '' }}>MA</option>
                        </select>
                        @error('level')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="program_id" class="form-label fw-semibold text-secondary">Program</label>
                        <select class="form-select @error('program_id') is-invalid @enderror" id="program_id" name="program_id" required>
                            <option value="">Pilih program...</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}" {{ (string) old('program_id', $schoolUnit->program_id) === (string) $program->id ? 'selected' : '' }}>{{ $program->name }}</option>
                            @endforeach
                        </select>
                        @error('program_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold text-secondary">Telepon</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $schoolUnit->phone) }}" placeholder="e.g. 0812-3456-7890">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="form_fee" class="form-label fw-semibold text-secondary">Biaya Formulir (Rp)</label>
                        <input type="number" min="0" step="1" class="form-control @error('form_fee') is-invalid @enderror" id="form_fee" name="form_fee" value="{{ old('form_fee', $schoolUnit->form_fee) }}" placeholder="0 berarti gratis">
                        @error('form_fee')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Biaya yang dibayar wali di awal pendaftaran, sebelum mengisi formulir. Berbeda tiap unit; isi 0 bila unit tidak memungut biaya formulir.</div>
                    </div>
<div class="mb-3">
                        <label for="address" class="form-label fw-semibold text-secondary">Alamat</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3" placeholder="Alamat unit">{{ old('address', $schoolUnit->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $schoolUnit->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.school-units.index') }}" class="btn btn-secondary">
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
