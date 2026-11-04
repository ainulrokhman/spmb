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

<form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4">
            <h5 class="mb-0 fw-bold text-body">Branding &amp; Halaman Awal</h5>
            <p class="text-muted small mb-0">Nama yayasan, tagline, logo, dan video profil yang tampil di halaman awal (landing page) dan navbar area wali. Semuanya opsional — kosong berarti memakai tampilan bawaan.</p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="mb-3">
                <label for="foundation_name" class="form-label fw-semibold text-secondary">Nama Yayasan</label>
                <input type="text" class="form-control" id="foundation_name" name="foundation_name" maxlength="120" value="{{ old('foundation_name', $settings['foundation_name']) }}" placeholder="mis. Yayasan Pendidikan Islam Al-Ikhlas">
            </div>

            <div class="mb-3">
                <label for="foundation_tagline" class="form-label fw-semibold text-secondary">Tagline</label>
                <input type="text" class="form-control" id="foundation_tagline" name="foundation_tagline" maxlength="200" value="{{ old('foundation_tagline', $settings['foundation_tagline']) }}" placeholder="mis. Membentuk generasi berakhlak dan berprestasi">
            </div>

            <div class="mb-3">
                <label for="site_logo" class="form-label fw-semibold text-secondary">Logo Yayasan (opsional)</label>
                @if($settings['site_logo'])
                    <div class="mb-2">
                        <img src="{{ asset('storage/'.$settings['site_logo']) }}" alt="Logo yayasan saat ini" style="height: 56px;">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                        <label class="form-check-label" for="remove_logo">Hapus logo saat ini</label>
                    </div>
                @endif
                <input type="file" class="form-control @error('site_logo') is-invalid @enderror" id="site_logo" name="site_logo" accept=".jpg,.jpeg,.png,.svg,.webp">
                @error('site_logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPG, PNG, SVG, atau WebP, maks 2MB. Disarankan PNG dengan latar transparan. Mengunggah berkas baru menggantikan logo lama.</div>
            </div>

            <div class="mb-0">
                <label for="profile_video_url" class="form-label fw-semibold text-secondary">Tautan Video Profil (YouTube, opsional)</label>
                <input type="url" class="form-control @error('profile_video_url') is-invalid @enderror" id="profile_video_url" name="profile_video_url" maxlength="500" value="{{ old('profile_video_url', $settings['profile_video_url']) }}" placeholder="mis. https://www.youtube.com/watch?v=xxxxxxxxxxx">
                @error('profile_video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Bila diisi dan berupa tautan video YouTube yang valid, video tampil tertanam di halaman awal.</div>
            </div>
        </div>
    </div>

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
