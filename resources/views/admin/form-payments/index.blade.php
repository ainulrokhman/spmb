@extends('layouts.admin')

@section('title', 'Pembayaran Formulir')
@section('page-title', 'Pembayaran Formulir')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Pembayaran Formulir</li>
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">Pengajuan Pembebasan &amp; Diskon</h5>
        <p class="text-muted small mb-0">Pengajuan wali pada tahap pembayaran formulir yang menunggu keputusan Anda</p>
    </div>
    <div class="card-body px-4 pb-4">
        @if($pendingRequests->isEmpty())
            <p class="text-muted small mb-0">Tidak ada pengajuan yang menunggu keputusan.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Wali / Anak</th>
                            <th class="text-secondary fw-semibold">Unit</th>
                            <th class="text-secondary fw-semibold">Pengajuan</th>
                            <th class="text-secondary fw-semibold">Alasan</th>
                            <th class="text-secondary fw-semibold" style="width: 380px;">Keputusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingRequests as $application)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $application->user?->name }}</div>
                                    <div class="text-muted small">{{ $application->user?->phone }} &middot; {{ $application->child_name ?? '—' }}</div>
                                </td>
                                <td>{{ $application->schoolUnit?->name }}<div class="text-muted small">Biaya: Rp {{ number_format($application->form_fee_amount, 0, ',', '.') }}</div></td>
                                <td>
                                    @if($application->form_fee_request_kind === 'waiver')
                                        <span class="badge bg-warning text-dark">Pembebasan penuh</span>
                                    @elseif($application->form_fee_request_kind === 'discount')
                                        <span class="badge bg-info">Diskon {{ $application->form_fee_request_type === 'percent' ? $application->form_fee_request_value.'%' : 'Rp '.number_format($application->form_fee_request_value, 0, ',', '.') }}</span>
                                    @else
                                        <span class="badge bg-info">Keringanan</span>
                                    @endif
                                </td>
                                <td class="small">{{ $application->form_fee_request_reason }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.form-payments.decide', $application) }}">
                                        @csrf
                                        <div class="d-flex gap-2 mb-2">
                                            <select name="relief_type" class="form-select form-select-sm" style="width: 150px;">
                                                <option value="waiver">Bebaskan penuh</option>
                                                <option value="percent">Diskon %</option>
                                                <option value="nominal">Diskon Rp</option>
                                            </select>
                                            <input type="number" name="relief_value" class="form-control form-control-sm" style="width: 120px;" min="1" placeholder="Nilai">
                                        </div>
                                        <div class="d-flex gap-2">
                                            <input type="text" name="note" class="form-control form-control-sm" placeholder="Catatan keputusan (opsional)" maxlength="500">
                                            <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm">Setujui</button>
                                            <button type="submit" name="decision" value="reject" class="btn btn-outline-danger btn-sm" onclick="return confirm('Tolak pengajuan ini?');">Tolak</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">Beri Keringanan Langsung</h5>
        <p class="text-muted small mb-0">Pendaftaran yang masih menunggu pembayaran formulir dan belum memiliki pengajuan — keringanan dapat diberikan atas inisiatif panitia</p>
    </div>
    <div class="card-body px-4 pb-4">
        @if($grantable->isEmpty())
            <p class="text-muted small mb-0">Tidak ada pendaftaran yang menunggu pembayaran formulir.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Wali / Anak</th>
                            <th class="text-secondary fw-semibold">Unit</th>
                            <th class="text-secondary fw-semibold" style="width: 460px;">Keringanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grantable as $application)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $application->user?->name }}</div>
                                    <div class="text-muted small">{{ $application->user?->phone }} &middot; {{ $application->child_name ?? '—' }}</div>
                                </td>
                                <td>{{ $application->schoolUnit?->name }}<div class="text-muted small">Biaya: Rp {{ number_format($application->form_fee_amount, 0, ',', '.') }}</div></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.form-payments.grant', $application) }}">
                                        @csrf
                                        <div class="d-flex gap-2 mb-2">
                                            <select name="relief_type" class="form-select form-select-sm" style="width: 150px;" required>
                                                <option value="waiver">Bebaskan penuh</option>
                                                <option value="percent">Diskon %</option>
                                                <option value="nominal">Diskon Rp</option>
                                            </select>
                                            <input type="number" name="relief_value" class="form-control form-control-sm" style="width: 120px;" min="1" placeholder="Nilai">
                                        </div>
                                        <div class="d-flex gap-2">
                                            <input type="text" name="note" class="form-control form-control-sm" placeholder="Catatan (opsional)" maxlength="500">
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Berikan keringanan ini?');">Beri Keringanan</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">Konfirmasi Pembayaran Manual</h5>
        <p class="text-muted small mb-0">Pembayaran manual yang sudah dinyatakan dibayar oleh wali dan menunggu konfirmasi penerimaan</p>
    </div>
    <div class="card-body px-4 pb-4">
        @if($awaitingConfirmations->isEmpty())
            <p class="text-muted small mb-0">Tidak ada pembayaran manual yang menunggu konfirmasi.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Referensi</th>
                            <th class="text-secondary fw-semibold">Wali / Anak</th>
                            <th class="text-secondary fw-semibold">Unit</th>
                            <th class="text-secondary fw-semibold">Nominal</th>
                            <th class="text-secondary fw-semibold text-end" style="width: 160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($awaitingConfirmations as $payment)
                            <tr>
                                <td class="small">{{ $payment->reference }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $payment->payable?->user?->name }}</div>
                                    <div class="text-muted small">{{ $payment->payable?->child_name ?? '—' }}</div>
                                </td>
                                <td>{{ $payment->payable?->schoolUnit?->name }}</td>
                                <td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.form-payments.confirm', $payment) }}" onsubmit="return confirm('Konfirmasi pembayaran ini sebagai lunas?');">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">Konfirmasi Lunas</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
