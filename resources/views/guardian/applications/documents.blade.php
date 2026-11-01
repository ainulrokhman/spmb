@extends('layouts.guardian')

@section('title', 'Input Berkas')

@section('content')
<h3 class="fw-bold mb-1">Tahap 3 — Input Berkas</h3>
<p class="text-muted mb-4">{{ $application->schoolUnit?->name }} &middot; {{ $application->child_name }}. Format yang diterima: JPG, PNG, atau PDF, maksimal 2MB per berkas.</p>

@if($errors->any())
    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3" role="alert">
        <strong>Periksa kembali berkas yang diunggah:</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        @forelse($requirements as $requirement)
            @php
                $type = $requirement->documentType;
                $doc = $uploaded->get($type->id);
            @endphp
            <div class="border rounded-3 p-3 mb-3">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">
                            {{ $type->name }}
                            @if($requirement->requirement === 'required')
                                <span class="badge bg-danger ms-1">Wajib</span>
                            @else
                                <span class="badge bg-secondary ms-1">Opsional</span>
                            @endif
                            @if($doc)
                                <span class="badge bg-success ms-1">Terunggah</span>
                            @endif
                        </div>
                        @if($type->description)
                            <div class="text-muted small">{{ $type->description }}</div>
                        @endif
                        @if($doc)
                            <div class="small mt-1">
                                <a href="{{ asset('storage/'.$doc->file_path) }}" target="_blank" rel="noopener">{{ $doc->original_name }}</a>
                                <span class="text-muted">({{ number_format($doc->size / 1024, 0) }} KB)</span>
                            </div>
                        @endif
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        <form method="POST" action="{{ route('guardian.applications.documents.upload', [$application, $type]) }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input type="file" name="file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf" required style="max-width: 240px;">
                            <button type="submit" class="btn btn-primary btn-sm">{{ $doc ? 'Ganti' : 'Unggah' }}</button>
                        </form>
                        @if($doc)
                            <form method="POST" action="{{ route('guardian.applications.documents.delete', [$application, $type]) }}" onsubmit="return confirm('Hapus berkas ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">Unit ini tidak mensyaratkan berkas apa pun. Anda dapat langsung mengirim pendaftaran.</p>
        @endforelse
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            @if($missing->isEmpty())
                <strong class="text-success">Semua berkas wajib sudah lengkap.</strong>
                <div class="text-muted small">Kirim pendaftaran untuk mendapatkan nomor pendaftaran. Setelah terkirim, pendaftaran menunggu verifikasi panitia.</div>
            @else
                <strong class="text-danger">Berkas wajib belum lengkap:</strong>
                <div class="text-muted small">{{ $missing->pluck('name')->implode(', ') }}</div>
            @endif
        </div>
        <form method="POST" action="{{ route('guardian.applications.submit', $application) }}" onsubmit="return confirm('Kirim pendaftaran sekarang? Pastikan data dan berkas sudah benar.');">
            @csrf
            <button type="submit" class="btn btn-success" {{ $missing->isEmpty() ? '' : 'disabled' }}>Kirim Pendaftaran</button>
        </form>
    </div>
</div>
@endsection
