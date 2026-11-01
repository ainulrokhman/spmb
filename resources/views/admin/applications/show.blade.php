@extends('layouts.admin')

@section('title', 'Detail Pendaftaran')
@section('page-title', 'Detail Pendaftaran')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.applications.index') }}" class="text-decoration-none">Verifikasi Pendaftaran</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $application->registration_number }}</li>
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

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-1">{{ $application->child_name }}</h5>
                        <p class="text-muted small mb-0">Nomor {{ $application->registration_number }} &middot; Dikirim {{ $application->submitted_at?->format('d M Y H:i') }}</p>
                    </div>
                    <span class="badge bg-primary fs-6">{{ $application->statusLabel() }}</span>
                </div>
                <hr>
                <div class="row small">
                    <div class="col-md-6">
                        <p class="mb-1"><span class="text-muted">Unit:</span> {{ $application->schoolUnit?->name }} ({{ $application->schoolUnit?->level }} &middot; {{ $application->schoolUnit?->program?->name }})</p>
                        <p class="mb-1"><span class="text-muted">Gelombang:</span> {{ $application->admissionWave?->name }} ({{ $application->admissionWave?->academicYear?->name }})</p>
                        <p class="mb-1"><span class="text-muted">Jalur:</span> {{ $application->admissionTrack?->name }}@if($application->major) &middot; {{ $application->major->name }}@endif</p>
                        <p class="mb-0"><span class="text-muted">Wali:</span> {{ $application->user?->name }} ({{ $application->user?->phone }}) — {{ \App\Models\Application::GUARDIAN_RELATIONS[$application->guardian_relation] ?? $application->guardian_relation }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><span class="text-muted">Tempat, Tgl Lahir:</span> {{ $application->birth_place }}, {{ $application->birth_date?->format('d M Y') }}</p>
                        <p class="mb-1"><span class="text-muted">Jenis Kelamin:</span> {{ $application->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
                        <p class="mb-1"><span class="text-muted">Biaya Formulir:</span>
                            @if($application->form_fee_amount === 0) Gratis
                            @else Rp {{ number_format($application->form_fee_amount, 0, ',', '.') }} — {{ $application->feeStatusLabel() }}
                                @if($application->discountAmount() > 0) (diskon Rp {{ number_format($application->discountAmount(), 0, ',', '.') }}) @endif
                            @endif
                        </p>
                        @if($application->verifier)
                            <p class="mb-0"><span class="text-muted">Keputusan oleh:</span> {{ $application->verifier->name }} pada {{ $application->verified_at?->format('d M Y H:i') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><h5 class="mb-0 fw-bold">Identitas Orang Tua</h5></div>
            <div class="card-body px-4 pb-4">
                @foreach(['father_data' => 'Ayah', 'mother_data' => 'Ibu', 'guardian_data' => 'Wali'] as $key => $label)
                    @if($application->{$key})
                        <h6 class="fw-bold">{{ $label }}</h6>
                        <div class="row small mb-3">
                            @foreach($application->{$key} as $field => $value)
                                @if($value !== null && $value !== '')
                                    <div class="col-md-4 mb-1"><span class="text-muted">{{ str_replace('_', ' ', ucfirst($field)) }}:</span> {{ is_array($value) ? implode(', ', $value) : $value }}</div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if($formFields->isNotEmpty())
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><h5 class="mb-0 fw-bold">Jawaban Formulir</h5></div>
                <div class="card-body px-4 pb-4">
                    <div class="row small">
                        @foreach($formFields as $field)
                            @php $value = $application->answers[$field->key] ?? null; @endphp
                            <div class="col-md-6 mb-2">
                                <span class="text-muted">{{ $field->label }}:</span>
                                @if($field->type === 'file' && is_string($value) && $value !== '')
                                    <a href="{{ asset('storage/'.$value) }}" target="_blank" rel="noopener">lihat berkas</a>
                                @elseif(is_array($value))
                                    {{ implode(', ', $value) }}
                                @else
                                    {{ $value ?? '—' }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold">Berkas</h5>
                <p class="text-muted small mb-0">Periksa setiap berkas, beri status, lalu ambil keputusan akhir di panel samping.</p>
            </div>
            <div class="card-body px-4 pb-4">
                @forelse($application->documents as $document)
                    @php $requirement = $requirements->get($document->document_type_id); @endphp
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <span class="fw-semibold">{{ $document->documentType?->name }}</span>
                                @if($requirement?->requirement === 'required')
                                    <span class="badge bg-danger ms-1">Wajib</span>
                                @else
                                    <span class="badge bg-secondary ms-1">Opsional</span>
                                @endif
                                <span class="badge {{ $document->verification_status === 'verified' ? 'bg-success' : ($document->verification_status === 'revision' ? 'bg-danger' : 'bg-warning text-dark') }} ms-1">{{ $document->verificationLabel() }}</span>
                                <div class="small mt-1">
                                    <a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" rel="noopener">{{ $document->original_name }}</a>
                                    <span class="text-muted">({{ number_format($document->size / 1024, 0) }} KB)</span>
                                </div>
                                @if($document->verification_note)
                                    <div class="small text-muted mt-1">Catatan: {{ $document->verification_note }}@if($document->verifier) — {{ $document->verifier->name }}@endif</div>
                                @endif
                            </div>

                            @if($application->status === 'submitted')
                                <form method="POST" action="{{ route('admin.applications.documents.verify', [$application, $document]) }}" class="d-flex gap-2 align-items-center">
                                    @csrf
                                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Catatan (untuk perbaikan)" maxlength="500" style="width: 210px;">
                                    <button type="submit" name="status" value="verified" class="btn btn-success btn-sm">Terverifikasi</button>
                                    <button type="submit" name="status" value="revision" class="btn btn-outline-danger btn-sm">Perlu Perbaikan</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada berkas yang diunggah.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><h5 class="mb-0 fw-bold">Keputusan Verifikasi</h5></div>
            <div class="card-body px-4 pb-4">
                @if($application->status === 'submitted')
                    @if($unverifiedRequired->isNotEmpty())
                        <div class="alert alert-warning bg-warning bg-opacity-10 text-body border-0 rounded-3 p-3 small">
                            Berkas wajib yang belum terverifikasi: <strong>{{ $unverifiedRequired->pluck('name')->implode(', ') }}</strong>. Keputusan "Terverifikasi" baru dapat diambil setelah semuanya diperiksa.
                        </div>
                    @else
                        <div class="alert alert-success bg-success bg-opacity-10 text-body border-0 rounded-3 p-3 small">
                            Semua berkas wajib sudah terverifikasi. Pendaftaran siap diputuskan sebagai Terverifikasi.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.applications.decide', $application) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="note" class="form-label fw-semibold">Catatan untuk Wali</label>
                            <textarea class="form-control" id="note" name="note" rows="3" placeholder="Wajib diisi untuk Perlu Perbaikan / Ditolak">{{ old('note') }}</textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="decision" value="verified" class="btn btn-success" {{ $unverifiedRequired->isNotEmpty() ? 'disabled' : '' }}>Terverifikasi</button>
                            <button type="submit" name="decision" value="needs_revision" class="btn btn-warning">Perlu Perbaikan</button>
                            <button type="submit" name="decision" value="rejected" class="btn btn-outline-danger" onclick="return confirm('Tolak pendaftaran ini secara keseluruhan?');">Ditolak</button>
                        </div>
                    </form>
                @else
                    <p class="small mb-2"><span class="text-muted">Status saat ini:</span> <strong>{{ $application->statusLabel() }}</strong></p>
                    @if($application->verification_note)
                        <p class="small mb-0"><span class="text-muted">Catatan panitia:</span> {{ $application->verification_note }}</p>
                    @endif
                    @if($application->status === 'needs_revision')
                        <p class="text-muted small mt-2 mb-0">Wali sedang memperbaiki. Keputusan baru dapat diambil setelah pendaftaran dikirim ulang.</p>
                    @endif
                @endif
            </div>
        </div>

        @if($application->status === 'verified')
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4"><h5 class="mb-0 fw-bold">Pengumuman Hasil</h5></div>
            <div class="card-body px-4 pb-4">
                <p class="text-muted small">Tetapkan hasil pendaftar ini. Hasil baru terlihat oleh wali setelah waktu pengumuman gelombang tiba{{ $application->admissionWave?->announcement_at ? ' ('.$application->admissionWave->announcement_at->format('d M Y H:i').')' : '' }}.</p>
                <form method="POST" action="{{ route('admin.applications.announce') }}">
                    @csrf
                    <input type="hidden" name="application_ids[]" value="{{ $application->id }}">
                    <div class="d-grid gap-2">
                        <button type="submit" name="decision" value="passed" class="btn btn-success">Lulus</button>
                        <button type="submit" name="decision" value="failed" class="btn btn-outline-danger" onclick="return confirm('Tetapkan pendaftar ini Tidak Lulus?');">Tidak Lulus</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
