@extends('layouts.admin')

@section('title', 'Hubungkan WhatsApp')
@section('page-title', 'Hubungkan Device WhatsApp')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}" class="text-decoration-none">WhatsApp</a></li>
    <li class="breadcrumb-item active" aria-current="page">Hubungkan</li>
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">{{ $device->label() }}</h5>
        <p class="text-muted small mb-0">
            {{ $device->isFoundation() ? 'Device yayasan (cadangan bersama)' : 'Device unit '.$device->schoolUnit?->name }}
            — status saat ini: @include('admin.whatsapp._status', ['device' => $device])
        </p>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-4 align-items-start">
            <div class="col-md-5 text-center">
                <img src="{{ route('admin.whatsapp.devices.qr', $device) }}" alt="Kode QR pemasangan WhatsApp" class="img-fluid border rounded-3 p-2" width="320" height="320">
                <div class="mt-2">
                    <a href="{{ route('admin.whatsapp.devices.login', $device) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Muat Ulang Kode QR
                    </a>
                </div>
                <p class="text-muted small mt-2 mb-0">Kode QR berlaku singkat. Bila kedaluwarsa atau gagal dipindai, muat ulang halaman ini.</p>
            </div>
            <div class="col-md-7">
                <h6 class="fw-bold">Cara memasangkan</h6>
                <ol class="mb-4">
                    <li>Ambil HP dengan nomor WhatsApp {{ $device->phone ?? 'device ini' }}.</li>
                    <li>Buka WhatsApp, masuk ke <strong>Perangkat Tertaut</strong> (Linked Devices), lalu ketuk <strong>Tautkan Perangkat</strong>.</li>
                    <li>Arahkan kamera HP ke kode QR di halaman ini.</li>
                    <li>Setelah berhasil, tekan tombol <strong>Periksa Status</strong> di bawah — status device harus berubah menjadi Terhubung.</li>
                </ol>
                <div class="d-flex flex-wrap gap-2">
                    <form action="{{ route('admin.whatsapp.devices.refresh', $device) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Periksa Status</button>
                    </form>
                    <a href="{{ route('admin.whatsapp.index') }}" class="btn btn-outline-secondary">Kembali</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
