@extends('layouts.guardian')

@section('title', 'Dasbor Saya')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Pendaftaran Anak Saya</h3>
        <p class="text-muted mb-0">Satu pendaftaran untuk setiap anak. Alur: Bayar Formulir → Isi Formulir → Input Berkas.</p>
    </div>
    <a href="{{ route('guardian.applications.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Daftarkan Anak
    </a>
</div>

@if($applications->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-people fs-1 text-muted"></i>
            <p class="text-muted mt-3">Belum ada pendaftaran. Mulai dengan menekan tombol "Daftarkan Anak".</p>
            <a href="{{ route('admissions.info') }}" class="btn btn-outline-primary">Lihat Unit yang Membuka Pendaftaran</a>
        </div>
    </div>
@else
    <div class="row g-3">
        @foreach($applications as $application)
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="fw-bold mb-1">{{ $application->child_name ?? 'Pendaftaran Baru' }}</h5>
                            <span class="badge bg-primary">{{ $application->statusLabel() }}</span>
                        </div>
                        <p class="text-muted small mb-2">
                            {{ $application->schoolUnit?->name }} &middot; {{ $application->admissionWave?->name }} ({{ $application->admissionWave?->academicYear?->name }}) &middot; {{ $application->admissionTrack?->name }}
                        </p>
                        @if($application->registration_number)
                            <p class="small mb-2">Nomor Pendaftaran: <strong>{{ $application->registration_number }}</strong></p>
                        @endif
                        <p class="small mb-3">
                            Biaya Formulir:
                            @if($application->form_fee_amount === 0)
                                <strong class="text-success">Gratis</strong>
                            @else
                                <strong>Rp {{ number_format($application->payableAmount(), 0, ',', '.') }}</strong>
                                <span class="text-muted">({{ $application->feeStatusLabel() }})</span>
                            @endif
                        </p>
                        <a href="{{ route('guardian.applications.show', $application) }}" class="btn btn-outline-primary btn-sm">
                            @if($application->registration_number)
                                Lihat Bukti
                            @else
                                Lanjutkan Pendaftaran
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
