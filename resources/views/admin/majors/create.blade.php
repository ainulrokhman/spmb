@extends('layouts.admin')

@section('title', 'Create Major')
@section('page-title', 'Create New Major')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.majors.index') }}" class="text-decoration-none">Majors</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.majors.store') }}" method="POST">
            @csrf

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Major Details</h5>
                    <p class="text-muted small mb-0">A major is only valid inside its owning unit</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3">
                        <label for="school_unit_id" class="form-label fw-semibold text-secondary">School Unit</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="school_unit_id" name="school_unit_id" required>
                            <option value="">Select unit...</option>
@foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('school_unit_id', '') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program }})</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-secondary">Major Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. IPA, IPS, Teknik Komputer dan Jaringan" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold text-secondary">Major Code</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" placeholder="e.g. IPA (optional)">
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-secondary" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.majors.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Create Major <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
