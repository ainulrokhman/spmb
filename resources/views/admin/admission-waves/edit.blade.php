@extends('layouts.admin')

@section('title', 'Ubah Gelombang')
@section('page-title', 'Ubah Gelombang: ' . $admissionWave->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.admission-waves.index') }}" class="text-decoration-none">Admission Waves</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.admission-waves.update', $admissionWave->id) }}" method="POST">
            @csrf
                    @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Detail Gelombang</h5>
                    <p class="text-muted small mb-0">A wave opens one or more tracks of its own unit</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(session('error'))
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="school_unit_id" class="form-label fw-semibold text-secondary">Unit Sekolah</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="school_unit_id" name="school_unit_id" required>
                            <option value="">Pilih unit...</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('school_unit_id', $admissionWave->school_unit_id ?? '') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program }})</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="academic_year_id" class="form-label fw-semibold text-secondary">Tahun Ajaran</label>
                        <select class="form-select @error('academic_year_id') is-invalid @enderror" id="academic_year_id" name="academic_year_id" required>
                            <option value="">Pilih tahun ajaran...</option>
                            @foreach($years as $year)
                                <option value="{{ $year->id }}" {{ (string) old('academic_year_id', $admissionWave->academic_year_id ?? '') === (string) $year->id ? 'selected' : '' }}>{{ $year->name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('academic_year_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Nama Gelombang</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $admissionWave->name ?? '') }}" placeholder="e.g. Gelombang 1" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="quota" class="form-label fw-semibold text-secondary">Kuota</label>
                        <input type="number" min="1" class="form-control @error('quota') is-invalid @enderror" id="quota" name="quota" value="{{ old('quota', $admissionWave->quota ?? '') }}" placeholder="Leave empty for unlimited">
                        @error('quota')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="opens_at" class="form-label fw-semibold text-secondary">Dibuka Pada</label>
                            <input type="datetime-local" class="form-control @error('opens_at') is-invalid @enderror" id="opens_at" name="opens_at" value="{{ old('opens_at', isset($admissionWave) ? $admissionWave->opens_at->format('Y-m-d\TH:i') : '') }}" required>
                            @error('opens_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="closes_at" class="form-label fw-semibold text-secondary">Ditutup Pada</label>
                            <input type="datetime-local" class="form-control @error('closes_at') is-invalid @enderror" id="closes_at" name="closes_at" value="{{ old('closes_at', isset($admissionWave) ? $admissionWave->closes_at->format('Y-m-d\TH:i') : '') }}" required>
                            @error('closes_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="announcement_at" class="form-label fw-semibold text-secondary">Waktu Pengumuman Hasil</label>
                            <input type="datetime-local" class="form-control @error('announcement_at') is-invalid @enderror" id="announcement_at" name="announcement_at" value="{{ old('announcement_at', isset($admissionWave) && $admissionWave->announcement_at ? $admissionWave->announcement_at->format('Y-m-d\\TH:i') : '') }}">
                            @error('announcement_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Hasil Lulus/Tidak Lulus baru terlihat oleh wali setelah waktu ini. Kosongkan bila belum dijadwalkan.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Jalur yang Dibuka</label>
                        @php($selectedTracks = old('tracks', isset($admissionWave) ? $admissionWave->admissionTracks->pluck('id')->all() : []))
                        <div id="track-groups">
                            @forelse($tracksByUnit as $unitId => $tracks)
                                <div class="track-group border rounded-3 p-3 mb-2" data-unit="{{ $unitId }}">
                                    @foreach($tracks as $track)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tracks[]" value="{{ $track->id }}" id="track-{{ $track->id }}" {{ in_array($track->id, $selectedTracks) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="track-{{ $track->id }}">{{ $track->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <p class="text-muted small mb-0">No admission tracks exist yet. Create tracks for a unit first.</p>
                            @endforelse
                        </div>
                        <p class="text-muted small mb-0" id="no-tracks-hint">Pilih unit sekolah untuk melihat jalurnya. Hanya jalur milik unit terpilih yang dapat dibuka.</p>
                        @error('tracks')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        @error('tracks.*')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $admissionWave->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Aktif (saklar manual)</label>
                        <div class="form-text text-muted small">Gelombang hanya menerima pendaftaran selama aktif dan berada dalam periode bukanya. Mematikan ini langsung menutup gelombang.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.admission-waves.index') }}" class="btn btn-secondary">
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

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const unitSelect = document.getElementById('school_unit_id');
        const groups = document.querySelectorAll('.track-group');
        const hint = document.getElementById('no-tracks-hint');

        function refreshTrackGroups() {
            let visibleTracks = 0;
            groups.forEach(group => {
                const matches = group.dataset.unit === unitSelect.value;
                group.classList.toggle('d-none', !matches);
                if (matches) {
                    visibleTracks += group.querySelectorAll('input[type="checkbox"]').length;
                } else {
                    group.querySelectorAll('input[type="checkbox"]').forEach(box => { box.checked = false; });
                }
            });
            hint.textContent = unitSelect.value
                ? (visibleTracks > 0 ? 'Only tracks of the chosen unit can be opened.' : 'This unit has no admission tracks yet.')
                : 'Pilih unit sekolah untuk melihat jalurnya. Hanya jalur milik unit terpilih yang dapat dibuka.';
        }

        unitSelect.addEventListener('change', refreshTrackGroups);
        refreshTrackGroups();
    });
</script>
@endpush
