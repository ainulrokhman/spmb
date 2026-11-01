@extends('layouts.admin')

@section('title', 'Laporan SPMB')
@section('page-title', 'Laporan SPMB')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Laporan SPMB</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body px-4 py-3">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="d-flex flex-column flex-lg-row align-items-lg-end gap-3">
            <div>
                <label for="academic_year_id" class="form-label small mb-1">Tahun Ajaran</label>
                <select id="academic_year_id" name="academic_year_id" class="form-select">
                    @foreach($years as $year)
                        <option value="{{ $year->id }}" {{ $yearId === $year->id ? 'selected' : '' }}>{{ $year->name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="school_unit_id" class="form-label small mb-1">Unit</label>
                <select id="school_unit_id" name="school_unit_id" class="form-select">
                    <option value="">Semua Unit</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" {{ $unitFilter === $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="admission_wave_id" class="form-label small mb-1">Gelombang</label>
                <select id="admission_wave_id" name="admission_wave_id" class="form-select">
                    <option value="">Semua Gelombang</option>
                    @foreach($waves as $wave)
                        <option value="{{ $wave->id }}" {{ $waveId === $wave->id ? 'selected' : '' }}>{{ $wave->schoolUnit?->name }} — {{ $wave->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="{{ route('admin.reports.export.applications', request()->query()) }}" class="btn btn-outline-success"><i class="bi bi-filetype-csv"></i> Export Pendaftar</a>
                @can('invoice-list')
                    <a href="{{ route('admin.reports.export.invoices', request()->query()) }}" class="btn btn-outline-success"><i class="bi bi-filetype-csv"></i> Export Tagihan</a>
                @endcan
            </div>
        </form>
    </div>
</div>

@forelse($recap as $row)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-body">{{ $row['unit']->name }}</h5>
                <small class="text-muted">{{ $row['unit']->level }} &middot; {{ $row['unit']->program?->name }} &middot; Kode {{ $row['unit']->code }}</small>
            </div>
            <span class="badge bg-primary fs-6">{{ $row['total_applicants'] }} pendaftar</span>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-4">
                <div class="col-md-4">
                    <h6 class="fw-bold">Per Gelombang</h6>
                    @if($row['by_wave'] === [])
                        <p class="text-muted small mb-0">Belum ada pendaftar.</p>
                    @else
                        <table class="table table-sm mb-0">
                            @foreach($row['by_wave'] as $name => $count)
                                <tr><td class="text-muted">{{ $name }}</td><td class="text-end fw-semibold">{{ $count }}</td></tr>
                            @endforeach
                        </table>
                    @endif
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold">Per Jalur</h6>
                    @if($row['by_track'] === [])
                        <p class="text-muted small mb-0">Belum ada pendaftar.</p>
                    @else
                        <table class="table table-sm mb-0">
                            @foreach($row['by_track'] as $name => $count)
                                <tr><td class="text-muted">{{ $name }}</td><td class="text-end fw-semibold">{{ $count }}</td></tr>
                            @endforeach
                        </table>
                    @endif
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold">Per Status</h6>
                    @if($row['by_status'] === [])
                        <p class="text-muted small mb-0">Belum ada pendaftar.</p>
                    @else
                        <table class="table table-sm mb-0">
                            @foreach($row['by_status'] as $key => $count)
                                <tr><td class="text-muted">{{ $statuses[$key] ?? $key }}</td><td class="text-end fw-semibold">{{ $count }}</td></tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            </div>

            <hr>
            <h6 class="fw-bold">Daftar Ulang &amp; Tagihan</h6>
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3">
                        <div class="fs-4 fw-bold">{{ $row['re_registered'] }}</div>
                        <div class="text-muted small">Sudah Daftar Ulang</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3">
                        <div class="fs-4 fw-bold">{{ $row['invoices_issued'] }}</div>
                        <div class="text-muted small">Tagihan Terbit ({{ $row['invoices_paid'] }} lunas, {{ $row['invoices_unpaid'] }} belum)</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3">
                        <div class="fs-5 fw-bold">Rp {{ number_format($row['billed_total'], 0, ',', '.') }}</div>
                        <div class="text-muted small">Total Tagihan (diskon Rp {{ number_format($row['discount_total'], 0, ',', '.') }})</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3">
                        <div class="fs-5 fw-bold">Rp {{ number_format($row['outstanding_total'], 0, ',', '.') }}</div>
                        <div class="text-muted small">Belum Dibayar</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">Tidak ada unit pada cakupan Anda.</div>
    </div>
@endforelse
@endsection
