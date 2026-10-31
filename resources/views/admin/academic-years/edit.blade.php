@extends('layouts.admin')

@section('title', 'Ubah Tahun Ajaran')
@section('page-title', 'Ubah Tahun Ajaran: ' . $academicYear->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.academic-years.index') }}" class="text-decoration-none">Academic Years</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.academic-years.update', $academicYear->id) }}" method="POST">
            @csrf
                    @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Tahun Ajaran</h5>
                    <p class="text-muted small mb-0">Set the academic year name, e.g. 2026/2027</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Nama Tahun Ajaran</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $academicYear->name) }}" placeholder="e.g. 2026/2027" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small">Mengaktifkan tahun ini otomatis menonaktifkan tahun ajaran aktif sebelumnya.</div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $academicYear->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.academic-years.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Simpan Perubahan <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
