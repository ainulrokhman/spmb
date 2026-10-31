@extends('layouts.admin')

@section('title', 'Fee Mappings')
@section('page-title', 'Fee Mappings: ' . $schoolUnit->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">School Units</a></li>
    <li class="breadcrumb-item active" aria-current="page">Fee Mappings</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-9 mx-auto">
        <form action="{{ route('admin.school-units.fees.update', $schoolUnit->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">Fee Mappings — {{ $schoolUnit->name }}</h5>
                    <p class="text-muted small mb-0">{{ $schoolUnit->level }} &middot; {{ $schoolUnit->program }}. Choose a mode per component: fixed (set by the system), pilihan (parents pick a tier), or bebas (parents fill the amount, optionally with a minimum). Amounts and tiers are managed under Fee Amounts.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if($errors->any())
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    @forelse($components as $component)
                        @php($mapping = $mappings->get($component->id))
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-3 border-bottom">
                            <div>
                                <span class="fw-semibold text-body d-block">{{ $component->name }}</span>
                                <span class="text-muted small">{{ $component->code }}@if($mapping) &middot; {{ $mapping->amounts_count }} nominal/tingkat @endif</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="number" min="0" step="1"
                                       name="mappings[{{ $component->id }}][min_amount]"
                                       value="{{ old('mappings.'.$component->id.'.min_amount', $mapping?->min_amount) }}"
                                       class="form-control" style="width: 170px;"
                                       placeholder="Minimum (bebas)"
                                       aria-label="Minimum amount for {{ $component->name }}">
                                <select name="mappings[{{ $component->id }}][mode]" class="form-select" style="width: 190px;" aria-label="Mode for {{ $component->name }}">
                                    <option value="unused" {{ old('mappings.'.$component->id.'.mode', $mapping?->mode ?? 'unused') === 'unused' ? 'selected' : '' }}>Tidak Dipakai</option>
                                    @foreach(\App\Models\Fee::MODES as $mode)
                                        <option value="{{ $mode }}" {{ old('mappings.'.$component->id.'.mode', $mapping?->mode ?? 'unused') === $mode ? 'selected' : '' }}>{{ ucfirst($mode) }}</option>
                                    @endforeach
                                </select>
                                @if($mapping)
                                    <a href="{{ route('admin.fee-amounts.index', ['school_unit_id' => $schoolUnit->id, 'fee_component_id' => $component->id]) }}" class="btn btn-light border py-2 px-3 fw-medium" style="border-radius: 8px;">
                                        <i class="bi bi-cash-stack text-success"></i> Nominal
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No active fee components exist yet. Create components under Fee Components first.</p>
                    @endforelse
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.school-units.index') }}" class="btn btn-light border py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary py-2 px-4 fw-medium" style="border-radius: 8px;">
                    Save Fee Mappings <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
