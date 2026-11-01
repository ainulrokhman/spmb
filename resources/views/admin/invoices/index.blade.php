@extends('layouts.admin')

@section('title', 'Tagihan Daftar Ulang')
@section('page-title', 'Tagihan Daftar Ulang')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Tagihan Daftar Ulang</li>
@endsection

@section('content')
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-body">Daftar Tagihan</h5>
            <p class="text-muted small mb-0">Tagihan daftar ulang pendaftar yang lulus, per unit tugas Anda</p>
        </div>
        <form method="GET" action="{{ route('admin.invoices.index') }}" class="d-flex align-items-center gap-2">
            <label for="status" class="form-label mb-0 small text-muted">Status</label>
            <select class="form-select form-select-sm" id="status" name="status" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="card-body px-4 pb-4">
        @if($invoices->isEmpty())
            <p class="text-muted small mb-0">Belum ada tagihan.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Anak / Wali</th>
                            <th class="text-secondary fw-semibold">Unit / Gelombang</th>
                            <th class="text-secondary fw-semibold text-end">Total Tagihan</th>
                            <th class="text-secondary fw-semibold text-end">Harus Dibayar</th>
                            <th class="text-secondary fw-semibold">Status</th>
                            <th class="text-secondary fw-semibold">Pengajuan</th>
                            <th class="text-secondary fw-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $invoice->application?->child_name }}</div>
                                    <div class="text-muted small">{{ $invoice->application?->user?->name }} &middot; {{ $invoice->application?->registration_number }}</div>
                                </td>
                                <td>{{ $invoice->application?->schoolUnit?->name }}<div class="text-muted small">{{ $invoice->application?->admissionWave?->name }}</div></td>
                                <td class="text-end">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format($invoice->payableAmount(), 0, ',', '.') }}</td>
                                <td><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $invoice->statusLabel() }}</span></td>
                                <td>
                                    @if($invoice->request_status === 'pending')
                                        <span class="badge bg-danger">Menunggu Keputusan</span>
                                    @elseif($invoice->request_status)
                                        <span class="text-muted small">{{ $invoice->request_status === 'approved' ? 'Disetujui' : 'Ditolak' }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
@endsection
