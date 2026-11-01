@extends('layouts.guardian')

@section('title', 'Info Pendaftaran')

@section('content')
<div class="text-center mb-4">
    <h3 class="fw-bold">Penerimaan Murid Baru</h3>
    <p class="text-muted mb-0">Unit yang sedang membuka pendaftaran. Alur pendaftaran: Daftar Akun → Bayar Formulir → Isi Formulir → Input Berkas.</p>
</div>

@if($units->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-megaphone fs-1 text-muted"></i>
            <p class="text-muted mt-3 mb-0">Saat ini belum ada unit yang membuka pendaftaran. Silakan periksa kembali nanti.</p>
        </div>
    </div>
@else
    <div class="row g-3">
        @foreach($units as $unit)
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="fw-bold mb-1">{{ $unit->name }}</h5>
                        <p class="text-muted small mb-3">{{ $unit->level }} &middot; {{ $unit->program?->name }} &middot; Kode {{ $unit->code }}</p>

                        @foreach($unit->getAttribute('open_waves') as $wave)
                            <div class="d-flex justify-content-between align-items-center border rounded-3 p-3 mb-2">
                                <div>
                                    <div class="fw-semibold">{{ $wave->name }}</div>
                                    <div class="text-muted small">{{ $wave->academicYear?->name }} &middot; {{ $wave->opens_at?->format('d M Y') }} – {{ $wave->closes_at?->format('d M Y') }}</div>
                                </div>
                                <span class="badge bg-success">Dibuka</span>
                            </div>
                        @endforeach

                        <p class="small mb-3">
                            Biaya formulir:
                            @if($unit->form_fee > 0)
                                <strong>Rp {{ number_format($unit->form_fee, 0, ',', '.') }}</strong>
                            @else
                                <strong class="text-success">Gratis</strong>
                            @endif
                        </p>

                        @auth
                            @if(auth()->user()->hasRole(\App\Support\SystemAccess::WALI_ROLE))
                                <a href="{{ route('guardian.applications.create', ['unit' => $unit->id]) }}" class="btn btn-primary">Daftar di Unit Ini</a>
                            @endif
                        @else
                            <a href="{{ route('guardian.register') }}" class="btn btn-primary">Daftar Akun untuk Mendaftar</a>
                        @endauth
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
