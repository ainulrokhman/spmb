@extends('layouts.guardian')

@section('title', 'Mulai Pendaftaran')

@section('content')
<h3 class="fw-bold mb-1">Mulai Pendaftaran</h3>
<p class="text-muted mb-4">Langkah 1 dari 3 tahap: pilih unit, gelombang, dan jalur. Setelah ini Anda menyelesaikan <strong>pembayaran formulir</strong>, lalu mengisi formulir, lalu mengunggah berkas.</p>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('guardian.applications.create') }}" class="row g-3">
            <div class="col-md-6">
                <label for="unit" class="form-label fw-semibold">Unit Sekolah</label>
                <select class="form-select" id="unit" name="unit" onchange="this.form.submit()">
                    <option value="">Pilih unit...</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" {{ $selectedUnit?->id === $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program?->name }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label for="wave" class="form-label fw-semibold">Gelombang yang Dibuka</label>
                <select class="form-select" id="wave" name="wave" onchange="this.form.submit()" {{ $selectedUnit === null ? 'disabled' : '' }}>
                    <option value="">Pilih gelombang...</option>
                    @foreach($waves as $wave)
                        <option value="{{ $wave->id }}" {{ $selectedWave?->id === $wave->id ? 'selected' : '' }}>
                            {{ $wave->name }} ({{ $wave->academicYear?->name }}) — s.d. {{ $wave->closes_at?->format('d M Y') }}
                        </option>
                    @endforeach
                </select>
                @if($selectedUnit !== null && $waves->isEmpty())
                    <div class="form-text text-danger">Unit ini belum memiliki gelombang yang sedang dibuka.</div>
                @endif
            </div>
        </form>
    </div>
</div>

@if($selectedUnit !== null && $selectedWave !== null)
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('guardian.applications.store') }}">
                @csrf
                <input type="hidden" name="school_unit_id" value="{{ $selectedUnit->id }}">
                <input type="hidden" name="admission_wave_id" value="{{ $selectedWave->id }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="admission_track_id" class="form-label fw-semibold">Jalur Pendaftaran <span class="text-danger">*</span></label>
                        <select class="form-select @error('admission_track_id') is-invalid @enderror" id="admission_track_id" name="admission_track_id" required>
                            <option value="">Pilih jalur...</option>
                            @foreach($selectedWave->admissionTracks as $track)
                                <option value="{{ $track->id }}" {{ (string) old('admission_track_id') === (string) $track->id ? 'selected' : '' }}>{{ $track->name }}</option>
                            @endforeach
                        </select>
                        @error('admission_track_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if($selectedUnit->majors->isNotEmpty())
                        <div class="col-md-6">
                            <label for="major_id" class="form-label fw-semibold">Jurusan</label>
                            <select class="form-select @error('major_id') is-invalid @enderror" id="major_id" name="major_id">
                                <option value="">Tanpa jurusan / belum memilih</option>
                                @foreach($selectedUnit->majors as $major)
                                    <option value="{{ $major->id }}" {{ (string) old('major_id') === (string) $major->id ? 'selected' : '' }}>{{ $major->name }}</option>
                                @endforeach
                            </select>
                            @error('major_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                </div>

                <div class="alert alert-info bg-info bg-opacity-10 text-body border-0 rounded-3 mt-4 mb-4 p-3">
                    Biaya formulir unit ini:
                    @if($selectedUnit->form_fee > 0)
                        <strong>Rp {{ number_format($selectedUnit->form_fee, 0, ',', '.') }}</strong> — dibayar sebelum mengisi formulir.
                    @else
                        <strong class="text-success">Gratis</strong> — Anda dapat langsung mengisi formulir.
                    @endif
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Mulai Pendaftaran</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
