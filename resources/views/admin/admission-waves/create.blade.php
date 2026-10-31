@extends('layouts.admin')

@section('title', 'Create Admission Wave')
@section('page-title', 'Create New Admission Wave')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.admission-waves.index') }}" class="text-decoration-none">Admission Waves</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.admission-waves.store') }}" method="POST">
            @csrf

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Admission Wave Details</h5>
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
                        <label for="school_unit_id" class="form-label fw-semibold text-secondary">School Unit</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="school_unit_id" name="school_unit_id" required>
                            <option value="">Select unit...</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('school_unit_id', $admissionWave->school_unit_id ?? '') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program }})</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="academic_year_id" class="form-label fw-semibold text-secondary">Academic Year</label>
                        <select class="form-select @error('academic_year_id') is-invalid @enderror" id="academic_year_id" name="academic_year_id" required>
                            <option value="">Select academic year...</option>
                            @foreach($years as $year)
                                <option value="{{ $year->id }}" {{ (string) old('academic_year_id', $admissionWave->academic_year_id ?? '') === (string) $year->id ? 'selected' : '' }}>{{ $year->name }}{{ $year->is_active ? ' (active)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('academic_year_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Wave Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $admissionWave->name ?? '') }}" placeholder="e.g. Gelombang 1" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="quota" class="form-label fw-semibold text-secondary">Quota</label>
                        <input type="number" min="1" class="form-control @error('quota') is-invalid @enderror" id="quota" name="quota" value="{{ old('quota', $admissionWave->quota ?? '') }}" placeholder="Leave empty for unlimited">
                        @error('quota')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="opens_at" class="form-label fw-semibold text-secondary">Opens At</label>
                            <input type="datetime-local" class="form-control @error('opens_at') is-invalid @enderror" id="opens_at" name="opens_at" value="{{ old('opens_at', isset($admissionWave) ? $admissionWave->opens_at->format('Y-m-d\TH:i') : '') }}" required>
                            @error('opens_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="closes_at" class="form-label fw-semibold text-secondary">Closes At</label>
                            <input type="datetime-local" class="form-control @error('closes_at') is-invalid @enderror" id="closes_at" name="closes_at" value="{{ old('closes_at', isset($admissionWave) ? $admissionWave->closes_at->format('Y-m-d\TH:i') : '') }}" required>
                            @error('closes_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Admission Tracks Opened</label>
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
                        <p class="text-muted small mb-0" id="no-tracks-hint">Select a school unit to see its tracks. Only tracks of the chosen unit can be opened.</p>
                        @error('tracks')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        @error('tracks.*')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $admissionWave->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Active (manual switch)</label>
                        <div class="form-text text-muted small">The wave accepts registrations only while active and inside its open period. Switching this off closes the wave immediately.</div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.admission-waves.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Create Admission Wave <i class="bi bi-check-lg ms-1"></i>
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
                : 'Select a school unit to see its tracks. Only tracks of the chosen unit can be opened.';
        }

        unitSelect.addEventListener('change', refreshTrackGroups);
        refreshTrackGroups();
    });
</script>
@endpush
