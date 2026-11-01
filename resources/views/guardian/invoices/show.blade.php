@extends('layouts.guardian')

@section('title', 'Daftar Ulang')

@section('content')
<h3 class="fw-bold mb-1">Daftar Ulang — {{ $application->child_name }}</h3>
<p class="text-muted mb-4">
    {{ $application->schoolUnit?->name }} &middot; {{ $application->admissionWave?->name }} &middot; {{ $application->admissionTrack?->name }}
    @if($application->registration_number)
        &middot; Nomor pendaftaran <strong>{{ $application->registration_number }}</strong>
    @endif
</p>

@if($application->status === 're_registered')
    <div class="alert alert-success bg-success bg-opacity-10 text-body border-0 rounded-3 p-3 mb-4">
        <strong>Daftar ulang terkonfirmasi.</strong> Selamat datang di {{ $application->schoolUnit?->name }}. Simpan halaman ini sebagai bukti.
    </div>
@endif

@if($invoice === null)
    {{-- Draft: resolve the unit's fees, let the guardian choose --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="fw-bold mb-1">Rincian Biaya Daftar Ulang</h5>
            <p class="text-muted small">Komponen bertanda <strong>pilihan</strong> perlu Anda pilih nominalnya, dan komponen <strong>bebas</strong> (mis. infaq) Anda isi sendiri. Tagihan dibekukan setelah Anda menekan tombol di bawah.</p>

            <form method="POST" action="{{ route('guardian.invoices.store', $application) }}">
                @csrf
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Komponen</th>
                            <th style="width: 45%;">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($draftLines as $line)
                            @php($fee = $line['fee'])
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $fee->feeComponent?->name }} <span class="text-muted">({{ $fee->feeComponent?->code }})</span></div>
                                    <div class="small text-muted">{{ \App\Models\Fee::MODES[$line['mode']] ?? $line['mode'] }}</div>
                                </td>
                                <td>
                                    @if($line['mode'] === 'fixed')
                                        Rp {{ number_format($line['fixed_amount'], 0, ',', '.') }}
                                    @elseif($line['mode'] === 'pilihan')
                                        @forelse($line['options'] as $option)
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="choice[{{ $fee->id }}]" id="choice-{{ $fee->id }}-{{ $option }}" value="{{ $option }}" {{ (int) old('choice.'.$fee->id) === $option ? 'checked' : '' }} required>
                                                <label class="form-check-label" for="choice-{{ $fee->id }}-{{ $option }}">Rp {{ number_format($option, 0, ',', '.') }}</label>
                                            </div>
                                        @empty
                                            <span class="text-muted small">Belum ada pilihan nominal — hubungi panitia.</span>
                                        @endforelse
                                    @else
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" name="free[{{ $fee->id }}]" min="{{ $line['min_amount'] ?? 0 }}" value="{{ old('free.'.$fee->id, $line['min_amount'] ?? 0) }}" required>
                                        </div>
                                        @if($line['min_amount'])
                                            <div class="form-text">Minimum Rp {{ number_format($line['min_amount'], 0, ',', '.') }}</div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">Unit ini belum memiliki pemetaan biaya. Hubungi panitia sekolah.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if($draftLines !== [])
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Buat Tagihan &amp; Lanjut ke Pembayaran</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@else
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Rincian Tagihan</h5>
                    <table class="table table-sm mb-0">
                        @foreach($invoice->items as $item)
                            <tr>
                                <td class="text-muted">{{ $item->component_name }} ({{ $item->component_code }})</td>
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
                            <td class="text-muted">Status</td>
                            <td class="text-end"><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $invoice->statusLabel() }}</span></td>
                        </tr>
                    </table>
                </div>
            </div>

            @if($invoice->request_status === 'pending')
                <div class="alert alert-warning bg-warning bg-opacity-10 text-body border-0 rounded-3 p-3">
                    <strong>Pengajuan {{ $invoice->request_kind === 'waiver' ? 'pembebasan' : 'diskon' }} tagihan sedang menunggu keputusan panitia.</strong>
                    <div class="small text-muted">Alasan Anda: {{ $invoice->request_reason }}</div>
                </div>
            @elseif($invoice->request_status === 'rejected')
                <div class="alert alert-danger bg-danger bg-opacity-10 text-body border-0 rounded-3 p-3">
                    <strong>Pengajuan Anda ditolak panitia.</strong>
                    @if($invoice->decision_note)
                        <div class="small text-muted">Catatan panitia: {{ $invoice->decision_note }}</div>
                    @endif
                </div>
            @endif

            @if($invoice->status !== 'paid')
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
                                    <form method="POST" action="{{ route('guardian.invoices.declare-paid', [$application, $activePayment]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">Saya Sudah Bayar</button>
                                    </form>
                                @elseif($activePayment->channel === 'manual')
                                    <p class="small mt-3 mb-0 text-muted">Menunggu konfirmasi panitia. Daftar ulang terkonfirmasi begitu pembayaran dikonfirmasi.</p>
                                @elseif($activePayment->channel === 'midtrans' && $activePayment->snap_token)
                                    <button type="button" class="btn btn-success btn-sm mt-3" id="pay-midtrans">Bayar Sekarang</button>
                                @endif
                            </div>
                        @endif

                        @if($activePayment === null)
                            <form method="POST" action="{{ route('guardian.invoices.pay', $application) }}">
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
            @endif
        </div>

        <div class="col-lg-5">
            @if($invoice->status !== 'paid')
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-2">Butuh Keringanan?</h5>
                        <p class="text-muted small">Anda dapat mengajukan pembebasan penuh atau diskon tagihan daftar ulang. Panitia akan memutuskan pengajuan ini sebelum Anda membayar.</p>

                        @if($invoice->request_status !== 'pending')
                            <form method="POST" action="{{ route('guardian.invoices.request-discount', $application) }}">
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
            @endif
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
@endif
@endsection
