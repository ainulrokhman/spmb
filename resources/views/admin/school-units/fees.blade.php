@extends('layouts.admin')

@section('title', 'Pemetaan Biaya')
@section('page-title', 'Pemetaan Biaya: ' . $schoolUnit->name)

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
                    <h5 class="mb-0 fw-bold text-body">Pemetaan Biaya — {{ $schoolUnit->name }}</h5>
                    <p class="text-muted small mb-0">{{ $schoolUnit->level }} &middot; {{ $schoolUnit->program?->name }}. Pilih mode per komponen: fixed (ditentukan sistem), pilihan (orang tua memilih tingkat), atau bebas (orang tua mengisi nominal, boleh dengan minimum). Nominal dan tingkat setiap komponen dapat ditambah serta dihapus langsung pada kartu di bawah halaman ini, atau dikelola lebih lanjut di menu Nominal Biaya.</p>
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
                                       aria-label="Jumlah minimum untuk {{ $component->name }}">
                                <select name="mappings[{{ $component->id }}][mode]" class="form-select" style="width: 190px;" aria-label="Mode untuk {{ $component->name }}">
                                    <option value="unused" {{ old('mappings.'.$component->id.'.mode', $mapping?->mode ?? 'unused') === 'unused' ? 'selected' : '' }}>Tidak Dipakai</option>
                                    @foreach(\App\Models\Fee::MODES as $mode)
                                        <option value="{{ $mode }}" {{ old('mappings.'.$component->id.'.mode', $mapping?->mode ?? 'unused') === $mode ? 'selected' : '' }}>{{ ['fixed' => 'Tetap (fixed)', 'pilihan' => 'Pilihan', 'bebas' => 'Bebas'][$mode] }}</option>
                                    @endforeach
                                </select>
                                @if($mapping)
                                    <a href="{{ route('admin.fee-amounts.index', ['school_unit_id' => $schoolUnit->id, 'fee_component_id' => $component->id]) }}" class="btn btn-light border py-2 px-3 fw-medium">
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
                <a href="{{ route('admin.school-units.index') }}" class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    Simpan Pemetaan <i class="bi bi-check-lg ms-1"></i>
                </button>
            </div>
        </form>

        <div class="card border-0 shadow-sm mb-4 mt-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Nominal &amp; Tingkat — {{ $schoolUnit->name }}</h5>
                <p class="text-muted small mb-0">Nominal setiap komponen pada unit ini. Aturan paling spesifik yang menang: jalur + jender, lalu jalur, lalu jender, lalu global (kosongkan jalur dan jender). Untuk mode pilihan, tambahkan beberapa nominal sebagai tingkat.</p>
            </div>
            <div class="card-body px-4 pb-4">
                @if(session('success'))
                    <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div>{{ session('error') }}</div>
                    </div>
                @endif

                @forelse($components as $component)
                    @php($mapping = $mappings->get($component->id))
                    @if($mapping)
                        <div class="py-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold text-body">{{ $component->name }}</span>
                                <span class="badge bg-secondary">{{ ['fixed' => 'Tetap (fixed)', 'pilihan' => 'Pilihan', 'bebas' => 'Bebas'][$mapping->mode] ?? $mapping->mode }}</span>
                                @if(in_array($mapping->mode, ['fixed', 'pilihan'], true) && $mapping->amounts_count === 0)
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Belum ada nominal — komponen ini belum dapat ditagihkan</span>
                                @endif
                            </div>

                            @foreach($mapping->amounts as $amount)
                                <div class="d-flex align-items-center justify-content-between gap-2 py-1">
                                    <span class="small">
                                        <span class="fw-semibold">Rp {{ number_format($amount->amount, 0, ',', '.') }}</span>
                                        <span class="text-muted">&middot; {{ $amount->admissionTrack?->name ?? 'Semua Jalur' }} &middot; {{ $amount->gender ? (\App\Models\FeeAmount::GENDERS[$amount->gender] ?? $amount->gender) : 'Semua Jender' }} &middot; urutan {{ $amount->sort_order }}</span>
                                    </span>
                                    <span class="d-flex gap-1">
                                        @can('fee-amount-edit')
                                            <a href="{{ route('admin.fee-amounts.edit', $amount->id) }}?return_unit_id={{ $schoolUnit->id }}" class="btn btn-sm btn-light border" title="Ubah Nominal"><i class="bi bi-pencil-square text-primary"></i></a>
                                        @endcan
                                        @can('fee-amount-delete')
                                            <form action="{{ route('admin.fee-amounts.destroy', $amount->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus nominal ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="return_unit_id" value="{{ $schoolUnit->id }}">
                                                <button type="submit" class="btn btn-sm btn-light border" title="Hapus Nominal"><i class="bi bi-trash text-danger"></i></button>
                                            </form>
                                        @endcan
                                    </span>
                                </div>
                            @endforeach

                            @can('fee-amount-create')
                                <form action="{{ route('admin.fee-amounts.store') }}" method="POST" class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                    @csrf
                                    <input type="hidden" name="fee_id" value="{{ $mapping->id }}">
                                    <input type="hidden" name="return_unit_id" value="{{ $schoolUnit->id }}">
                                    <input type="number" name="amount" class="form-control form-control-sm" style="width: 160px;" placeholder="Nominal (Rp)" min="0" step="1" required aria-label="Nominal baru untuk {{ $component->name }}">
                                    <select name="admission_track_id" class="form-select form-select-sm" style="width: 170px;" aria-label="Jalur untuk nominal baru">
                                        <option value="">Semua Jalur</option>
                                        @foreach($tracks as $track)
                                            <option value="{{ $track->id }}">{{ $track->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="gender" class="form-select form-select-sm" style="width: 150px;" aria-label="Jender untuk nominal baru">
                                        <option value="">Semua Jender</option>
                                        @foreach(\App\Models\FeeAmount::GENDERS as $genderCode => $genderLabel)
                                            <option value="{{ $genderCode }}">{{ $genderLabel }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="sort_order" class="form-control form-control-sm" style="width: 90px;" placeholder="Urutan" value="0" aria-label="Urutan nominal baru">
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Tambah</button>
                                </form>
                            @endcan
                        </div>
                    @endif
                @empty
                    <p class="text-muted mb-0">Belum ada komponen biaya aktif.</p>
                @endforelse

                @if($mappings->isEmpty())
                    <p class="text-muted mb-0">Belum ada pemetaan biaya pada unit ini. Atur mode komponen pada kartu Pemetaan Biaya di atas terlebih dahulu, lalu nominal dapat ditambahkan di sini.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
