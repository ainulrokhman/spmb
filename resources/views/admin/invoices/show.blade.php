@extends('layouts.admin')

@section('title', 'Detail Tagihan')
@section('page-title', 'Detail Tagihan Daftar Ulang')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index') }}">Tagihan Daftar Ulang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
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

@php($application = $invoice->application)

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">{{ $application?->child_name }}</h5>
                <p class="text-muted small mb-0">
                    Wali: {{ $application?->user?->name }} ({{ $application?->user?->phone }}) &middot;
                    {{ $application?->schoolUnit?->name }} &middot; {{ $application?->admissionWave?->name }} &middot; {{ $application?->admissionTrack?->name }} &middot;
                    Nomor {{ $application?->registration_number }}
                </p>
            </div>
            <div class="card-body px-4 pb-4">
                <table class="table table-sm mb-0">
                    @foreach($invoice->items as $item)
                        <tr>
                            <td class="text-muted">{{ $item->component_name }} ({{ $item->component_code }}) — {{ \App\Models\Fee::MODES[$item->mode] ?? $item->mode }}</td>
                            <td class="text-end">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    @if($invoice->discountAmount() > 0)
                        <tr>
                            <td class="text-muted">Diskon ({{ \App\Models\Application::DISCOUNT_TYPES[$invoice->discount_type] ?? '' }} {{ $invoice->discount_type === 'percent' ? $invoice->discount_value.'%' : '' }})</td>
                            <td class="text-end text-success">− Rp {{ number_format($invoice->discountAmount(), 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    <tr class="fw-bold">
                        <td>Yang harus dibayar</td>
                        <td class="text-end">Rp {{ number_format($invoice->payableAmount(), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status Tagihan</td>
                        <td class="text-end"><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $invoice->statusLabel() }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status Pendaftaran</td>
                        <td class="text-end">{{ $application?->statusLabel() }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Riwayat Pembayaran</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if($invoice->payments->isEmpty())
                    <p class="text-muted small mb-0">Belum ada pembayaran.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-secondary fw-semibold">Referensi</th>
                                    <th class="text-secondary fw-semibold">Kanal</th>
                                    <th class="text-secondary fw-semibold text-end">Nominal</th>
                                    <th class="text-secondary fw-semibold">Status</th>
                                    <th class="text-secondary fw-semibold"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->reference }}</td>
                                        <td>{{ $payment->channel }}</td>
                                        <td class="text-end">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                        <td>{{ $payment->statusLabel() }}</td>
                                        <td class="text-end">
                                            @if($payment->channel === 'manual' && $payment->status === 'awaiting_confirmation')
                                                <form method="POST" action="{{ route('admin.invoices.confirm', $payment) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">Konfirmasi Lunas</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Pengajuan Keringanan</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if($invoice->request_status === 'pending')
                    <p class="small mb-2">
                        <strong>{{ $invoice->request_kind === 'waiver' ? 'Pembebasan penuh' : 'Diskon' }}</strong>
                        @if($invoice->request_kind === 'discount')
                            — {{ \App\Models\Application::DISCOUNT_TYPES[$invoice->request_type] ?? '' }}
                            {{ $invoice->request_type === 'percent' ? $invoice->request_value.'%' : 'Rp '.number_format($invoice->request_value ?? 0, 0, ',', '.') }}
                        @endif
                    </p>
                    <p class="small text-muted">Alasan wali: {{ $invoice->request_reason }}</p>
                    <form method="POST" action="{{ route('admin.invoices.decide-discount', $invoice) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="note" class="form-label fw-semibold text-secondary">Catatan Keputusan</label>
                            <textarea class="form-control" id="note" name="note" rows="2" maxlength="500" placeholder="Opsional"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="decision" value="approve" class="btn btn-success">Setujui</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-outline-danger">Tolak</button>
                        </div>
                    </form>
                @elseif($invoice->request_status)
                    <p class="small mb-1">Pengajuan terakhir: <strong>{{ $invoice->request_status === 'approved' ? 'Disetujui' : 'Ditolak' }}</strong>@if($invoice->decider) oleh {{ $invoice->decider->name }}@endif.</p>
                    @if($invoice->decision_note)
                        <p class="small text-muted mb-0">Catatan: {{ $invoice->decision_note }}</p>
                    @endif
                @else
                    <p class="text-muted small mb-0">Tidak ada pengajuan keringanan pada tagihan ini.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
