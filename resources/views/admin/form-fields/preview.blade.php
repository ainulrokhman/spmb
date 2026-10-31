@extends('layouts.admin')

@section('title', 'Form Preview')
@section('page-title', 'Form Preview: ' . $schoolUnit->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.form-fields.index') }}" class="text-decoration-none">Form Fields</a></li>
    <li class="breadcrumb-item active" aria-current="page">Preview</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 mx-auto">
        <div class="alert alert-info bg-info bg-opacity-10 text-info border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
            <i class="bi bi-eye-fill me-2 fs-5"></i>
            <div>Preview of the dynamic fields for <strong>{{ $schoolUnit->name }}</strong> ({{ $schoolUnit->level }} &middot; {{ $schoolUnit->program }}). Core identity fields are fixed and shown separately in the real form. All inputs here are disabled.</div>
        </div>

        @forelse($fieldGroups as $section => $fields)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">{{ $section }}</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    @foreach($fields as $field)
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary">
                                {{ $field->label }}
                                @if($field->is_required)
                                    <span class="text-danger">*</span>
                                @endif
                            </label>

                            @switch($field->type)
                                @case('textarea')
                                    <textarea class="form-control" rows="3" disabled></textarea>
                                    @break
                                @case('number')
                                    <input type="number" class="form-control" disabled>
                                    @break
                                @case('date')
                                    <input type="date" class="form-control" disabled>
                                    @break
                                @case('select')
                                    <select class="form-select" disabled>
                                        <option value="">Pilih...</option>
                                        @foreach($field->options ?? [] as $option)
                                            <option>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('radio')
                                    @foreach($field->options ?? [] as $option)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" disabled>
                                            <label class="form-check-label">{{ $option }}</label>
                                        </div>
                                    @endforeach
                                    @break
                                @case('checkbox')
                                    @foreach($field->options ?? [] as $option)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" disabled>
                                            <label class="form-check-label">{{ $option }}</label>
                                        </div>
                                    @endforeach
                                    @break
                                @case('file')
                                    <input type="file" class="form-control" disabled>
                                    @break
                                @default
                                    <input type="text" class="form-control" disabled>
                            @endswitch

                            @if($field->help_text)
                                <div class="form-text text-muted small">{{ $field->help_text }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center text-muted">
                    This unit has no active form fields yet.
                </div>
            </div>
        @endforelse

        <div class="d-flex justify-content-end">
            <a href="{{ route('admin.form-fields.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                Back to Form Fields
            </a>
        </div>
    </div>
</div>
@endsection
