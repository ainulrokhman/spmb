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
                    @if($invoice->paidAmount() > 0)
                        <tr>
                            <td class="text-muted">Sudah dibayar</td>
                            <td class="text-end text-success">Rp {{ number_format($invoice->paidAmount(), 0, ',', '.') }}</td>
                        </tr>
                        <tr class="fw-bold">
                            <td>Sisa tagihan</td>
                            <td class="text-end">Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</td>
                        </tr>
                    @endif
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
                @if($invoice->payments->isEmpty() && $invoice->installments->every(fn ($i) => $i->payments->isEmpty()))
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
                                @foreach($invoice->installments as $installment)
                                    @foreach($installment->payments as $payment)
                                        <tr>
                                            <td>{{ $payment->reference }} <span class="text-muted small">(Termin {{ $installment->sequence }})</span></td>
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
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Pengajuan Keringanan</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if($invoice->request_status === 'pending')
                    @if($invoice->request_kind)
                        <p class="small mb-2">
                            <strong>{{ $invoice->request_kind === 'waiver' ? 'Pembebasan penuh' : 'Diskon' }}</strong>
                            @if($invoice->request_kind === 'discount')
                                — {{ \App\Models\Application::DISCOUNT_TYPES[$invoice->request_type] ?? '' }}
                                {{ $invoice->request_type === 'percent' ? $invoice->request_value.'%' : 'Rp '.number_format($invoice->request_value ?? 0, 0, ',', '.') }}
                            @endif
                        </p>
                    @endif
                    <p class="small text-muted">Alasan wali: {{ $invoice->request_reason }}</p>
                    <form method="POST" action="{{ route('admin.invoices.decide-discount', $invoice) }}">
                        @csrf
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="relief_type" class="form-label fw-semibold text-secondary">Bentuk Keringanan</label>
                                <select class="form-select" id="relief_type" name="relief_type">
                                    <option value="waiver">Bebaskan penuh</option>
                                    <option value="percent">Diskon persen</option>
                                    <option value="nominal">Diskon nominal</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="relief_value" class="form-label fw-semibold text-secondary">Nilai</label>
                                <input type="number" class="form-control" id="relief_value" name="relief_value" min="1" placeholder="% atau Rp">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label fw-semibold text-secondary">Catatan Keputusan</label>
                            <textarea class="form-control" id="note" name="note" rows="2" maxlength="500" placeholder="Opsional"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="decision" value="approve" class="btn btn-success">Setujui</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-outline-danger" formnovalidate>Tolak</button>
                        </div>
                    </form>
                @elseif($invoice->discount_type !== null)
                    <p class="small mb-1">Keringanan sudah diberikan: <strong>{{ \App\Models\Application::DISCOUNT_TYPES[$invoice->discount_type] ?? '' }} {{ $invoice->discount_type === 'percent' ? $invoice->discount_value.'%' : 'Rp '.number_format($invoice->discount_value ?? 0, 0, ',', '.') }}</strong>@if($invoice->decider) oleh {{ $invoice->decider->name }}@endif.</p>
                    <p class="small text-muted mb-0">{{ $invoice->request_status === 'approved' ? 'Bermula dari pengajuan wali yang disetujui.' : 'Diberikan langsung oleh panitia tanpa pengajuan.' }}@if($invoice->decision_note) Catatan: {{ $invoice->decision_note }}@endif</p>
                @elseif($invoice->request_status)
                    <p class="small mb-1">Pengajuan terakhir: <strong>Ditolak</strong>@if($invoice->decider) oleh {{ $invoice->decider->name }}@endif.</p>
                    @if($invoice->decision_note)
                        <p class="small text-muted mb-0">Catatan: {{ $invoice->decision_note }}</p>
                    @endif
                @elseif($invoice->status !== 'paid' && $invoice->installments->isEmpty())
                    <p class="small text-muted">Tidak ada pengajuan. Berikan keringanan langsung:</p>
                    <form method="POST" action="{{ route('admin.invoices.grant-discount', $invoice) }}">
                        @csrf
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="grant_relief_type" class="form-label fw-semibold text-secondary">Bentuk Keringanan</label>
                                <select class="form-select" id="grant_relief_type" name="relief_type" required>
                                    <option value="waiver">Bebaskan penuh</option>
                                    <option value="percent">Diskon persen</option>
                                    <option value="nominal">Diskon nominal</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="grant_relief_value" class="form-label fw-semibold text-secondary">Nilai</label>
                                <input type="number" class="form-control" id="grant_relief_value" name="relief_value" min="1" placeholder="% atau Rp">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="grant_note" class="form-label fw-semibold text-secondary">Catatan</label>
                            <textarea class="form-control" id="grant_note" name="note" rows="2" maxlength="500" placeholder="Opsional"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Berikan keringanan ini?');">Beri Keringanan</button>
                    </form>
                @else
                    <p class="text-muted small mb-0">Tidak ada pengajuan keringanan pada tagihan ini.</p>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Cicilan</h5>
            </div>
            <div class="card-body px-4 pb-4">
                @if($invoice->installment_request_status === 'pending')
                    <p class="small text-muted">Alasan wali: {{ $invoice->installment_request_reason }}</p>
                    <form method="POST" action="{{ route('admin.invoices.decide-installment', $invoice) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label fw-semibold text-secondary">Rincian Termin</label>
                            <div class="form-text mb-2">Total rincian harus sama dengan sisa tagihan: <strong>Rp {{ number_format($invoice->payableAmount(), 0, ',', '.') }}</strong>. Rincian hanya Anda yang menentukan.</div>
                            <div id="termin-rows">
                                <div class="d-flex gap-2 mb-2 termin-row">
                                    <input type="number" name="installments[0][amount]" class="form-control" min="1" placeholder="Nominal termin" required>
                                    <input type="date" name="installments[0][due_date]" class="form-control" required>
                                    <button type="button" class="btn btn-outline-danger remove-termin" title="Hapus termin">&times;</button>
                                </div>
                            </div>
                            <button type="button" id="add-termin" class="btn btn-sm btn-outline-secondary">+ Tambah Termin</button>
                        </div>
                        <div class="mb-3">
                            <label for="installment_note" class="form-label fw-semibold text-secondary">Catatan Keputusan</label>
                            <textarea class="form-control" id="installment_note" name="note" rows="2" maxlength="500" placeholder="Opsional"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="decision" value="approve" class="btn btn-success">Setujui &amp; Buat Rincian</button>
                            <button type="submit" name="decision" value="reject" class="btn btn-outline-danger" formnovalidate>Tolak</button>
                        </div>
                    </form>
                @elseif($invoice->installments->isNotEmpty())
                    <p class="small text-muted">{{ $invoice->installments->where('status', 'paid')->count() }} dari {{ $invoice->installments->count() }} termin lunas.@if($invoice->installmentDecider) Disetujui oleh {{ $invoice->installmentDecider->name }}.@endif Termin yang belum lunas masih dapat diubah; totalnya harus tetap Rp {{ number_format($invoice->payableAmount(), 0, ',', '.') }}.</p>
                    <form method="POST" action="{{ route('admin.invoices.revise-installments', $invoice) }}">
                        @csrf
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-secondary fw-semibold">Termin</th>
                                    <th class="text-secondary fw-semibold">Nominal</th>
                                    <th class="text-secondary fw-semibold">Jatuh Tempo</th>
                                    <th class="text-secondary fw-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->installments as $installment)
                                    <tr>
                                        <td>{{ $installment->sequence }}</td>
                                        <td>
                                            @if($installment->status === 'paid')
                                                <span class="small">Rp {{ number_format($installment->amount, 0, ',', '.') }}</span>
                                            @else
                                                <input type="number" name="rows[{{ $installment->id }}][amount]" class="form-control form-control-sm" min="1" value="{{ $installment->amount }}" required>
                                            @endif
                                        </td>
                                        <td>
                                            @if($installment->status === 'paid')
                                                <span class="small">{{ $installment->due_date?->format('d/m/Y') }}</span>
                                            @else
                                                <input type="date" name="rows[{{ $installment->id }}][due_date]" class="form-control form-control-sm" value="{{ $installment->due_date?->format('Y-m-d') }}" required>
                                            @endif
                                        </td>
                                        <td><span class="badge {{ $installment->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $installment->statusLabel() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($invoice->status !== 'paid')
                            <button type="submit" class="btn btn-primary btn-sm">Simpan Rincian</button>
                        @endif
                    </form>
                @elseif($invoice->installment_request_status === 'rejected')
                    <p class="small mb-1">Pengajuan cicilan terakhir: <strong>Ditolak</strong>@if($invoice->installmentDecider) oleh {{ $invoice->installmentDecider->name }}@endif.</p>
                    @if($invoice->installment_decision_note)
                        <p class="small text-muted mb-0">Catatan: {{ $invoice->installment_decision_note }}</p>
                    @endif
                @else
                    <p class="text-muted small mb-0">Tidak ada pengajuan cicilan pada tagihan ini. Rincian cicilan hanya dibuat dari pengajuan wali yang disetujui.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.getElementById('termin-rows');
        const addBtn = document.getElementById('add-termin');
        if (!rows || !addBtn) {
            return;
        }
        let nextIndex = rows.children.length;
        addBtn.addEventListener('click', function () {
            const div = document.createElement('div');
            div.className = 'd-flex gap-2 mb-2 termin-row';
            div.innerHTML = '<input type="number" name="installments[' + nextIndex + '][amount]" class="form-control" min="1" placeholder="Nominal termin" required>' +
                '<input type="date" name="installments[' + nextIndex + '][due_date]" class="form-control" required>' +
                '<button type="button" class="btn btn-outline-danger remove-termin" title="Hapus termin">&times;</button>';
            rows.appendChild(div);
            nextIndex++;
        });
        rows.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-termin') && rows.children.length > 1) {
                event.target.closest('.termin-row').remove();
            }
        });
    });
</script>
@endpush

@endsection
