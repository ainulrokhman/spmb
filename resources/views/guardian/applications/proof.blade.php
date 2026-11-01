@extends('layouts.guardian')

@section('title', 'Bukti Pendaftaran')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <h3 class="fw-bold mb-0">Bukti Pendaftaran</h3>
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak Bukti
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="text-center border-bottom pb-3 mb-3">
            <h4 class="fw-bold mb-1">BUKTI PENDAFTARAN SPMB</h4>
            <p class="text-muted mb-0">{{ $application->schoolUnit?->name }} &middot; Tahun Ajaran {{ $application->admissionWave?->academicYear?->name }}</p>
        </div>

        <div class="text-center mb-4">
            <div class="text-muted small">Nomor Pendaftaran</div>
            <div class="display-6 fw-bold">{{ $application->registration_number }}</div>
            @php($announced = $application->admissionWave !== null && $application->admissionWave->resultsAnnounced())
            @php($resultHidden = in_array($application->status, ['passed', 'failed'], true) && ! $announced)
            <span class="badge bg-primary">{{ $resultHidden ? 'Menunggu Pengumuman' : $application->statusLabel() }}</span>
            @if($application->status === 'passed' && $announced)
                <div class="mt-2 fw-semibold text-success">Lulus — silakan lakukan daftar ulang dari Dasbor Saya.</div>
            @endif
        </div>

        <table class="table table-sm">
            <tr><td class="text-muted" style="width: 220px;">Nama Anak</td><td class="fw-semibold">{{ $application->child_name }}</td></tr>
            <tr><td class="text-muted">Tempat, Tanggal Lahir</td><td>{{ $application->birth_place }}, {{ $application->birth_date?->format('d M Y') }}</td></tr>
            <tr><td class="text-muted">Jenis Kelamin</td><td>{{ $application->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</td></tr>
            <tr><td class="text-muted">Unit</td><td>{{ $application->schoolUnit?->name }} ({{ $application->schoolUnit?->level }} &middot; {{ $application->schoolUnit?->program?->name }})</td></tr>
            <tr><td class="text-muted">Gelombang</td><td>{{ $application->admissionWave?->name }}</td></tr>
            <tr><td class="text-muted">Jalur</td><td>{{ $application->admissionTrack?->name }}</td></tr>
            @if($application->major)
                <tr><td class="text-muted">Jurusan</td><td>{{ $application->major->name }}</td></tr>
            @endif
            <tr><td class="text-muted">Nama Wali Pendaftar</td><td>{{ $application->user?->name }} ({{ \App\Models\Application::GUARDIAN_RELATIONS[$application->guardian_relation] ?? $application->guardian_relation }})</td></tr>
            <tr><td class="text-muted">Biaya Formulir</td><td>
                @if($application->form_fee_amount === 0)
                    Gratis
                @else
                    Rp {{ number_format($application->form_fee_amount, 0, ',', '.') }} — {{ $application->feeStatusLabel() }}
                    @if($application->discountAmount() > 0)
                        (setelah diskon Rp {{ number_format($application->discountAmount(), 0, ',', '.') }}, dibayar Rp {{ number_format($application->payableAmount(), 0, ',', '.') }})
                    @endif
                @endif
            </td></tr>
            <tr><td class="text-muted">Dikirim Pada</td><td>{{ $application->submitted_at?->format('d M Y H:i') }} WIB</td></tr>
        </table>

        @if($application->documents->isNotEmpty())
            <h6 class="fw-bold mt-4">Berkas Terunggah</h6>
            <ul class="small mb-0">
                @foreach($application->documents as $doc)
                    <li>{{ $doc->documentType?->name }} — {{ $doc->original_name }}</li>
                @endforeach
            </ul>
        @endif

        <p class="text-muted small mt-4 mb-0">Simpan bukti ini. Status pendaftaran dapat dipantau dari Dasbor Saya. Pengumuman hasil verifikasi akan disampaikan melalui akun wali.</p>
    </div>
</div>
@endsection
