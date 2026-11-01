@extends('layouts.guardian')

@section('title', 'Bayar Formulir')

@section('content')
<h3 class="fw-bold mb-1">Tahap 1 — Bayar Formulir</h3>
<p class="text-muted mb-4">{{ $application->schoolUnit?->name }} &middot; {{ $application->admissionWave?->name }} &middot; {{ $application->admissionTrack?->name }}. Formulir pendaftaran terbuka setelah tahap ini selesai.</p>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Rincian Biaya Formulir</h5>
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted">Biaya formulir unit</td>
                        <td class="text-end">Rp {{ number_format($application->form_fee_amount, 0, ',', '.') }}</td>
                    </tr>
                    @if($application->discountAmount() > 0)
                        <tr>
                            <td class="text-muted">Diskon ({{ \App\Models\Application::DISCOUNT_TYPES[$application->form_fee_discount_type] ?? '' }} {{ $application->form_fee_discount_type === 'percent' ? $application->form_fee_discount_value.'%' : '' }})</td>
                            <td class="text-end text-success">− Rp {{ number_format($application->discountAmount(), 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    <tr class="fw-bold">
                        <td>Yang harus dibayar</td>
                        <td class="text-end">Rp {{ number_format($application->payableAmount(), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td class="text-end"><span class="badge bg-warning text-dark">{{ $application->feeStatusLabel() }}</span></td>
                    </tr>
                </table>
            </div>
        </div>

        @if($application->form_fee_request_status === 'pending')
            <div class="alert alert-warning bg-warning bg-opacity-10 text-body border-0 rounded-3 p-3">
                <strong>Pengajuan {{ $application->form_fee_request_kind === 'waiver' ? 'pembebasan' : 'diskon' }} sedang menunggu keputusan panitia.</strong>
                <div class="small text-muted">Alasan Anda: {{ $application->form_fee_request_reason }}</div>
            </div>
        @elseif($application->form_fee_request_status === 'rejected')
            <div class="alert alert-danger bg-danger bg-opacity-10 text-body border-0 rounded-3 p-3">
                <strong>Pengajuan Anda ditolak panitia.</strong>
                @if($application->form_fee_decision_note)
                    <div class="small text-muted">Catatan panitia: {{ $application->form_fee_decision_note }}</div>
                @endif
                <div class="small">Silakan selesaikan pembayaran biaya formulir untuk melanjutkan.</div>
            </div>
        @elseif($application->form_fee_request_status === 'approved' && $application->discountAmount() > 0)
            <div class="alert alert-success bg-success bg-opacity-10 text-body border-0 rounded-3 p-3">
                <strong>Pengajuan diskon disetujui panitia.</strong> Bayar sisa nominal di samping melalui kanal pembayaran.
            </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Pilih Kanal Pembayaran</h5>

                @if($activePayment !== null)
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $channels[$activePayment->channel]->label() ?? $activePayment->channel }}</div>
                                <div class="text-muted small">Referensi: {{ $activePayment->reference }} &middot; {{ $activePayment->statusLabel() }}</div>
                            </div>
                            <span class="badge bg-info">{{ $activePayment->statusLabel() }}</span>
                        </div>

                        @if($activePayment->channel === 'manual' && $activePayment->status === 'pending')
                            <p class="small mt-3 mb-2">{{ \App\Models\Setting::get('payment_manual_instructions', 'Transfer ke rekening yayasan atau bayar tunai di sekolah, lalu tekan tombol di bawah agar panitia dapat mengonfirmasi.') }}</p>
                            <form method="POST" action="{{ route('guardian.applications.declare-paid', [$application, $activePayment]) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">Saya Sudah Bayar</button>
                            </form>
                        @elseif($activePayment->channel === 'manual')
                            <p class="small mt-3 mb-0 text-muted">Menunggu konfirmasi panitia. Formulir akan terbuka begitu pembayaran dikonfirmasi.</p>
                        @elseif($activePayment->channel === 'midtrans' && $activePayment->snap_token)
                            <button type="button" class="btn btn-success btn-sm mt-3" id="pay-midtrans">Bayar Sekarang</button>
                            <p class="small mt-2 mb-0 text-muted">Setelah pembayaran berhasil, halaman ini akan terbuka ke tahap formulir secara otomatis saat Anda kembali.</p>
                        @endif
                    </div>
                @endif

                @if($activePayment === null)
                    <form method="POST" action="{{ route('guardian.applications.pay', $application) }}">
                        @csrf
                        @forelse($channels as $key => $channel)
                            <div class="form-check border rounded-3 p-3 mb-2 ms-0">
                                <input class="form-check-input ms-0 me-2" type="radio" name="channel" id="channel-{{ $key }}" value="{{ $key }}" required>
                                <label class="form-check-label fw-semibold" for="channel-{{ $key }}">{{ $channel->label() }}</label>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Belum ada kanal pembayaran yang aktif. Hubungi panitia sekolah.</p>
                        @endforelse

                        @if($channels !== [])
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary">Buat Pembayaran</button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold mb-2">Butuh Keringanan?</h5>
                <p class="text-muted small">Calon siswa khusus dapat mengajukan pembebasan penuh atau diskon biaya formulir. Panitia akan memutuskan pengajuan ini sebelum Anda membayar.</p>

                @if($application->form_fee_request_status !== 'pending')
                    <form method="POST" action="{{ route('guardian.applications.request-relief', $application) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="kind" class="form-label fw-semibold">Jenis Pengajuan</label>
                            <select class="form-select" id="kind" name="kind" required>
                                <option value="waiver" {{ old('kind') === 'waiver' ? 'selected' : '' }}>Pembebasan penuh</option>
                                <option value="discount" {{ old('kind') === 'discount' ? 'selected' : '' }}>Diskon sebagian</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label for="type" class="form-label fw-semibold">Tipe Diskon</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">—</option>
                                    <option value="percent" {{ old('type') === 'percent' ? 'selected' : '' }}>Persen (%)</option>
                                    <option value="nominal" {{ old('type') === 'nominal' ? 'selected' : '' }}>Nominal (Rp)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="value" class="form-label fw-semibold">Nilai</label>
                                <input type="number" min="1" class="form-control" id="value" name="value" value="{{ old('value') }}" placeholder="mis. 50 atau 25000">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="reason" class="form-label fw-semibold">Alasan Pengajuan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required placeholder="mis. Anak yatim, keluarga tidak mampu, anak karyawan yayasan">{{ old('reason') }}</textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">Kirim Pengajuan</button>
                        </div>
                    </form>
                @else
                    <p class="small text-muted mb-0">Pengajuan Anda sedang diproses panitia. Anda belum dapat mengajukan lagi sampai ada keputusan.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@if(session('payment_view.type') === 'snap' || ($activePayment?->channel === 'midtrans' && $activePayment?->snap_token))
    @push('scripts')
        <script src="{{ session('payment_view.snap_js_url', config('midtrans.snap_js_url')) }}" data-client-key="{{ session('payment_view.client_key', config('midtrans.client_key')) }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const openSnap = function () {
                    if (window.snap) {
                        window.snap.pay('{{ session('payment_view.snap_token', $activePayment?->snap_token) }}', {
                            onSuccess: function () { window.location.reload(); },
                            onPending: function () { window.location.reload(); },
                            onError: function () { window.location.reload(); }
                        });
                    }
                };
                const btn = document.getElementById('pay-midtrans');
                if (btn) {
                    btn.addEventListener('click', openSnap);
                }
                @if(session('payment_view.type') === 'snap')
                    openSnap();
                @endif
            });
        </script>
    @endpush
@endif
@endsection
