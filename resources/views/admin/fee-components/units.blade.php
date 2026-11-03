@extends('layouts.admin')

@section('title', 'Atur per Unit: ' . $feeComponent->name)
@section('page-title', 'Atur per Unit — ' . $feeComponent->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-components.index') }}" class="text-decoration-none">Komponen Biaya</a></li>
    <li class="breadcrumb-item active" aria-current="page">Atur per Unit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-10 mx-auto">
        <form action="{{ route('admin.fee-components.units.update', $feeComponent->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold text-body">{{ $feeComponent->name }} <span class="text-muted fw-normal">({{ $feeComponent->code }})</span></h5>
                    <p class="text-muted small mb-0">Mode komponen ini untuk setiap unit dalam satu layar. Mode <em>bebas</em> dapat memiliki nominal minimum. Baris bertanda peringatan berarti modenya aktif tetapi belum ada nominal/tingkat — isi lewat tautan Nominal pada baris tersebut.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @if(session('success'))
                        <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div>{{ session('success') }}</div>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    @forelse($units as $unit)
                        @php($mapping = $mappings->get($unit->id))
                        @php($needsAmounts = $mapping && in_array($mapping->mode, ['fixed', 'pilihan'], true) && $mapping->amounts_count === 0)
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-3 border-bottom">
                            <div>
                                <span class="fw-semibold text-body d-block">{{ $unit->name }}</span>
                                <span class="text-muted small">{{ $unit->level }} &middot; {{ $unit->program?->name }}@if($mapping) &middot; {{ $mapping->amounts_count }} nominal/tingkat @endif</span>
                                @if($needsAmounts)
                                    <span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-triangle"></i> Belum ada nominal</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="number" min="0" step="1"
                                       name="units[{{ $unit->id }}][min_amount]"
                                       value="{{ old('units.'.$unit->id.'.min_amount', $mapping?->min_amount) }}"
                                       class="form-control" style="width: 170px;"
                                       placeholder="Minimum (bebas)"
                                       aria-label="Jumlah minimum untuk {{ $unit->name }}">
                                <select name="units[{{ $unit->id }}][mode]" class="form-select" style="width: 190px;" aria-label="Mode {{ $feeComponent->name }} untuk {{ $unit->name }}">
                                    <option value="unused" {{ old('units.'.$unit->id.'.mode', $mapping?->mode ?? 'unused') === 'unused' ? 'selected' : '' }}>Tidak Dipakai</option>
                                    @foreach(\App\Models\Fee::MODES as $mode)
                                        <option value="{{ $mode }}" {{ old('units.'.$unit->id.'.mode', $mapping?->mode ?? 'unused') === $mode ? 'selected' : '' }}>{{ ['fixed' => 'Tetap (fixed)', 'pilihan' => 'Pilihan', 'bebas' => 'Bebas'][$mode] }}</option>
                                    @endforeach
                                </select>
                                @if($mapping)
                                    <a href="{{ route('admin.fee-amounts.index', ['school_unit_id' => $unit->id, 'fee_component_id' => $feeComponent->id]) }}" class="btn btn-light border py-2 px-3 fw-medium">
                                        <i class="bi bi-cash-stack text-success"></i> Nominal
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Tidak ada unit yang dapat Anda kelola.</p>
                    @endforelse
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.fee-components.index') }}" class="btn btn-secondary">Kembali</a>
                <button type="submit" class="btn btn-primary">Simpan Semua <i class="bi bi-check-lg ms-1"></i></button>
            </div>
        </form>
    </div>
</div>
@endsection
