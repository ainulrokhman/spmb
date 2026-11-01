@extends('layouts.admin')

@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif

<form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold text-body">Kanal Pembayaran</h5>
            <p class="text-muted small mb-0">Kanal yang dapat dipilih wali saat membayar biaya formulir dan tagihan. Kanal baru (mis. BSI custom) ditambahkan sebagai driver tersendiri.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="payment_manual_enabled" name="payment_manual_enabled" value="1" {{ $settings['payment_manual_enabled'] ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="payment_manual_enabled">Manual (Transfer / Tunai)</label>
                <div class="form-text">Wali membayar sesuai instruksi, panitia mengonfirmasi penerimaan di sistem.</div>
            </div>

            <div class="mb-4">
                <label for="payment_manual_instructions" class="form-label fw-semibold text-secondary">Instruksi Pembayaran Manual</label>
                <textarea class="form-control" id="payment_manual_instructions" name="payment_manual_instructions" rows="3" placeholder="mis. Transfer ke BSI 1234567890 a.n. Yayasan, lalu tekan tombol Saya Sudah Bayar.">{{ old('payment_manual_instructions', $settings['payment_manual_instructions']) }}</textarea>
            </div>

            <hr>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="payment_midtrans_enabled" name="payment_midtrans_enabled" value="1" {{ $settings['payment_midtrans_enabled'] ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="payment_midtrans_enabled">Midtrans (VA / E-Wallet / QRIS)</label>
                <div class="form-text">
                    @if($midtransConfigured)
                        Kunci Midtrans terdeteksi di konfigurasi server (.env).
                    @else
                        <span class="text-danger">Kunci Midtrans belum diatur di .env (MIDTRANS_SERVER_KEY &amp; MIDTRANS_CLIENT_KEY); kanal tidak akan tampil sebelum kunci terisi.</span>
                    @endif
                </div>
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" disabled>
                <label class="form-check-label fw-semibold text-muted">BSI (Custom)</label>
                <div class="form-text">Belum tersedia — menunggu akses/spesifikasi API dari Bank Syariah Indonesia.</div>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-end gap-3 mb-5">
        <button type="submit" class="btn btn-primary">
            Simpan Pengaturan <i class="bi bi-check-lg ms-1"></i>
        </button>
    </div>
</form>
@endsection
