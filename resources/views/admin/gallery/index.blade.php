@extends('layouts.admin')

@section('title', 'Galeri')
@section('page-title', 'Galeri Halaman Awal')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Galeri</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">Tambah Foto</h5>
        <p class="text-muted small mb-0">Foto kegiatan yayasan/sekolah yang tampil sebagai slide di halaman awal. JPG, PNG, atau WebP, maks 5MB per foto; dapat memilih banyak foto sekaligus.</p>
    </div>
    <div class="card-body px-4 pb-4">
        <form action="{{ route('admin.gallery.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="photos" class="form-label fw-semibold text-secondary">Berkas Foto</label>
                    <input type="file" class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror" id="photos" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required>
                    @error('photos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('photos.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="caption" class="form-label fw-semibold text-secondary">Keterangan (opsional, untuk semua foto)</label>
                    <input type="text" class="form-control" id="caption" name="caption" maxlength="200" value="{{ old('caption') }}" placeholder="mis. Kegiatan belajar di kelas">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Unggah</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">Daftar Foto ({{ $photos->count() }})</h5>
        <p class="text-muted small mb-0">Urutan terkecil tampil lebih dulu. Foto nonaktif tersimpan tetapi tidak tampil di halaman awal.</p>
    </div>
    <div class="card-body px-4 pb-4">
        @if($photos->isEmpty())
            <p class="text-muted mb-0">Belum ada foto. Bagian galeri tidak tampil di halaman awal sampai ada foto aktif.</p>
        @else
            <div class="row g-3">
                @foreach($photos as $photo)
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 {{ $photo->is_active ? '' : 'opacity-75' }}">
                            <img src="{{ $photo->url() }}" class="card-img-top" alt="{{ $photo->caption ?? 'Foto galeri' }}" style="height: 180px; object-fit: cover;">
                            <div class="card-body">
                                <form action="{{ route('admin.gallery.update', $photo) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold text-secondary" for="caption-{{ $photo->id }}">Keterangan</label>
                                        <input type="text" class="form-control form-control-sm" id="caption-{{ $photo->id }}" name="caption" maxlength="200" value="{{ $photo->caption }}">
                                    </div>
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div>
                                            <label class="form-label small fw-semibold text-secondary" for="sort-{{ $photo->id }}">Urutan</label>
                                            <input type="number" class="form-control form-control-sm" id="sort-{{ $photo->id }}" name="sort_order" min="0" max="9999" value="{{ $photo->sort_order }}" style="width: 90px;">
                                        </div>
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="active-{{ $photo->id }}" name="is_active" value="1" {{ $photo->is_active ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="active-{{ $photo->id }}">Aktif</label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check-lg"></i> Simpan</button>
                                </form>
                                <form action="{{ route('admin.gallery.destroy', $photo) }}" method="POST" class="mt-2" onsubmit="return confirm('Hapus foto ini dari galeri?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
