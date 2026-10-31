@extends('layouts.admin')

@section('title', 'Edit Admission Track')
@section('page-title', 'Edit Admission Track: ' . $admissionTrack->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.admission-tracks.index') }}" class="text-decoration-none">Admission Tracks</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.admission-tracks.update', $admissionTrack->id) }}" method="POST">
            @csrf
                    @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Admission Track Details</h5>
                    <p class="text-muted small mb-0">A track is only valid inside its owning unit</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="school_unit_id" class="form-label fw-semibold text-secondary">School Unit</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="school_unit_id" name="school_unit_id" required>
                            <option value="">Select unit...</option>
@foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('school_unit_id', $admissionTrack->school_unit_id) === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program }})</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Track Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $admissionTrack->name) }}" placeholder="e.g. Prestasi, Reguler, Afirmasi" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold text-secondary">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Optional description of this track">{{ old('description', $admissionTrack->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $admissionTrack->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.admission-tracks.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Save Changes <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
