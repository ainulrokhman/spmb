@extends('layouts.admin')

@section('title', 'Ubah Template WhatsApp')
@section('page-title', 'Ubah Template — '.$template->typeLabel())

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.templates.index') }}">Template Pesan</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $template->typeLabel() }}</li>
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
        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body px-4 py-4">
                <form action="{{ route('admin.whatsapp.templates.update', $template->type) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="content" class="form-label fw-semibold">Isi Pesan</label>
                        <textarea name="content" id="content" rows="9" class="form-control @error('content') is-invalid @enderror" required maxlength="2000">{{ old('content', $template->content) }}</textarea>
                        @error('content')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Gunakan <code>*teks*</code> untuk huruf tebal ala WhatsApp. Variabel di bawah dapat disisipkan dan akan diganti otomatis saat pengiriman.</div>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $template->is_active))>
                        <label class="form-check-label" for="is_active">Template aktif (pesan jenis ini dikirim)</label>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                        <a href="{{ route('admin.whatsapp.templates.index') }}" class="btn btn-outline-secondary">Kembali</a>
                    </div>
                </form>
                <hr>
                <form action="{{ route('admin.whatsapp.templates.restore', $template->type) }}" method="POST" onsubmit="return confirm('Kembalikan isi template ini ke teks bawaan?')">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i> Pulihkan Teks Bawaan</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h6 class="mb-0 fw-bold text-body"><i class="bi bi-braces me-2"></i>Variabel Tersedia</h6>
            </div>
            <div class="card-body px-4 pb-4">
                @forelse($variables as $key => $label)
                    <div class="d-flex justify-content-between gap-2 py-1">
                        <code>{{ '{'.$key.'}' }}</code>
                        <span class="text-muted small text-end">{{ $label }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Tidak ada variabel untuk jenis ini.</p>
                @endforelse
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h6 class="mb-0 fw-bold text-body"><i class="bi bi-eye me-2"></i>Pratinjau (data contoh)</h6>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="bg-success bg-opacity-10 rounded-3 p-3 small" style="white-space: pre-wrap;">{{ $template->preview() }}</div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h6 class="mb-0 fw-bold text-body"><i class="bi bi-send me-2"></i>Kirim Pesan Uji</h6>
                <p class="text-muted small mb-0">Mengirim pratinjau di atas ke nomor tujuan melalui device yayasan.</p>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('admin.whatsapp.templates.test', $template->type) }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" required maxlength="20">
                    <button type="submit" class="btn btn-outline-success text-nowrap"><i class="bi bi-whatsapp"></i> Kirim</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
