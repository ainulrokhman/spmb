@extends('layouts.admin')

@section('title', 'Laporan WhatsApp')
@section('page-title', 'Laporan WhatsApp')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Laporan WhatsApp</li>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Total Pesan</div>
            <div class="fs-3 fw-bold">{{ number_format($summary['total']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Terkirim</div>
            <div class="fs-3 fw-bold text-success">{{ number_format($summary['sent']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Gagal</div>
            <div class="fs-3 fw-bold text-danger">{{ number_format($summary['failed']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Dilewati</div>
            <div class="fs-3 fw-bold text-secondary">{{ number_format($summary['skipped']) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Terkirim via Cadangan Yayasan</div>
            <div class="fs-3 fw-bold text-info">{{ number_format($summary['via_foundation']) }}</div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body px-4 py-4">
        <form method="GET" action="{{ route('admin.whatsapp.report') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="date_from" class="form-label fw-semibold text-secondary">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label fw-semibold text-secondary">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label for="school_unit_id" class="form-label fw-semibold text-secondary">Unit</label>
                    <select class="form-select" id="school_unit_id" name="school_unit_id">
                        <option value="">Semua</option>
                        <option value="yayasan" @selected(($filters['school_unit_id'] ?? '') === 'yayasan')>Yayasan</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected(($filters['school_unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="whatsapp_device_id" class="form-label fw-semibold text-secondary">Device Pengirim</label>
                    <select class="form-select" id="whatsapp_device_id" name="whatsapp_device_id">
                        <option value="">Semua</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->id }}" @selected(($filters['whatsapp_device_id'] ?? '') == $device->id)>{{ $device->isFoundation() ? 'Yayasan' : $device->schoolUnit?->name }} — {{ $device->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="type" class="form-label fw-semibold text-secondary">Jenis</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Semua</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold text-secondary">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="q" class="form-label fw-semibold text-secondary">Cari</label>
                    <input type="text" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nomor, nama penerima, atau isi pesan">
                </div>
                <div class="col-md-8 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Terapkan Filter</button>
                    <a href="{{ route('admin.whatsapp.report') }}" class="btn btn-outline-secondary">Atur Ulang</a>
                    <a href="{{ route('admin.whatsapp.report.export', $filters) }}" class="btn btn-outline-success"><i class="bi bi-filetype-csv"></i> Ekspor CSV</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body px-4 py-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-secondary fw-semibold">Waktu</th>
                        <th class="text-secondary fw-semibold">Penerima</th>
                        <th class="text-secondary fw-semibold">Unit</th>
                        <th class="text-secondary fw-semibold">Device Pengirim</th>
                        <th class="text-secondary fw-semibold">Jenis</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th class="text-secondary fw-semibold">Isi Pesan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $log->recipient_name ?? '—' }}</div>
                                <div class="text-muted small">{{ $log->recipient_phone }}</div>
                            </td>
                            <td>{{ $log->schoolUnit?->name ?? 'Yayasan' }}</td>
                            <td>
                                @if($log->device)
                                    {{ $log->device->label() }}
                                    <span class="badge text-bg-info ms-1">{{ $log->device->providerLabel() }}</span>
                                    @if($log->sentViaFoundationFallback())
                                        <span class="badge text-bg-warning ms-1">via cadangan</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $log->typeLabel() }}</td>
                            <td>
                                @php
                                    $badge = match ($log->status) {
                                        \App\Models\WhatsappMessageLog::STATUS_SENT => 'text-bg-success',
                                        \App\Models\WhatsappMessageLog::STATUS_FAILED => 'text-bg-danger',
                                        default => 'text-bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ $log->statusLabel() }}</span>
                                @if($log->error)
                                    <div class="text-danger small mt-1">{{ $log->error }}</div>
                                @endif
                            </td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($log->message, 120) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada pesan yang tercatat sesuai filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
