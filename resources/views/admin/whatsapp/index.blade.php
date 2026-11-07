@extends('layouts.admin')

@section('title', 'WhatsApp')
@section('page-title', 'Perangkat WhatsApp')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">WhatsApp</li>
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body"><i class="bi bi-building me-2"></i>Device Yayasan (Cadangan)</h5>
        <p class="text-muted small mb-0">Nomor bersama tingkat yayasan. Dipakai oleh unit yang belum mempunyai device sendiri, dan menjadi cadangan bila device unit sedang tidak terhubung.</p>
    </div>
    <div class="card-body px-4 pb-4">
        @if($foundation)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="fw-semibold">{{ $foundation->label() }}</span>
                <span class="badge text-bg-info">{{ $foundation->providerLabel() }}</span>
                @if($foundation->phone)
                    <span class="text-muted small">{{ $foundation->phone }}</span>
                @endif
                @include('admin.whatsapp._status', ['device' => $foundation])
            </div>
            @include('admin.whatsapp._actions', ['device' => $foundation])
        @else
            <p class="text-muted mb-0">Belum ada device yayasan. Tambahkan lewat formulir <strong>Tambah Device</strong> di bawah dengan pilihan "Untuk: Yayasan".</p>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body"><i class="bi bi-phone me-2"></i>Device per Unit</h5>
        <p class="text-muted small mb-0">Setiap unit boleh memakai nomornya sendiri. Unit tanpa device mengirim lewat device yayasan.</p>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-secondary fw-semibold">Unit</th>
                        <th class="text-secondary fw-semibold">Device</th>
                        <th class="text-secondary fw-semibold">Nomor</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th class="text-secondary fw-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($units as $unit)
                        <tr>
                            <td class="fw-semibold">{{ $unit->name }}</td>
                            @if($unit->whatsappDevice)
                                <td>
                                    {{ $unit->whatsappDevice->label() }}
                                    <span class="badge text-bg-info ms-1">{{ $unit->whatsappDevice->providerLabel() }}</span>
                                </td>
                                <td>{{ $unit->whatsappDevice->phone ?? '—' }}</td>
                                <td>@include('admin.whatsapp._status', ['device' => $unit->whatsappDevice])</td>
                                <td>@include('admin.whatsapp._actions', ['device' => $unit->whatsappDevice])</td>
                            @else
                                <td colspan="3" class="text-muted">Belum ada device — memakai device yayasan</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#tambah-device" aria-expanded="false" onclick="document.getElementById('scope').value='{{ $unit->id }}'">
                                        <i class="bi bi-plus-lg"></i> Tambah Device
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">
            <button type="button" class="btn btn-link text-decoration-none fw-bold text-body p-0" data-bs-toggle="collapse" data-bs-target="#tambah-device" aria-expanded="false">
                <i class="bi bi-plus-circle me-2"></i>Tambah Device
            </button>
        </h5>
        <p class="text-muted small mb-0">GOWA: slot device dibuat di server GOWA milik yayasan, lalu dipasangkan dengan kode QR. Fonnte: nomor dipasangkan dulu di dashboard Fonnte, lalu token device-nya ditempel di sini.</p>
    </div>
    <div class="collapse" id="tambah-device">
        <div class="card-body px-4 pb-4">
            <form action="{{ route('admin.whatsapp.devices.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="scope" class="form-label fw-semibold text-secondary">Untuk</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="scope" name="school_unit_id">
                            <option value="" @selected($foundation) @disabled($foundation)>Yayasan (cadangan bersama){{ $foundation ? ' — sudah ada' : '' }}</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected(old('school_unit_id') == $unit->id) @disabled($unit->whatsappDevice)>{{ $unit->name }}{{ $unit->whatsappDevice ? ' — sudah ada device' : '' }}</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="provider" class="form-label fw-semibold text-secondary">Provider</label>
                        <select class="form-select @error('provider') is-invalid @enderror" id="provider" name="provider" required>
                            @foreach($providers as $key => $label)
                                <option value="{{ $key }}" @selected(old('provider') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="display_name" class="form-label fw-semibold text-secondary">Nama Tampilan (opsional)</label>
                        <input type="text" class="form-control @error('display_name') is-invalid @enderror" id="display_name" name="display_name" maxlength="100" value="{{ old('display_name') }}" placeholder="mis. WA SMP">
                        @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="token" class="form-label fw-semibold text-secondary">Token Device Fonnte (khusus Fonnte)</label>
                        <input type="text" class="form-control @error('token') is-invalid @enderror" id="token" name="token" maxlength="500" value="{{ old('token') }}" placeholder="Tempel token dari dashboard Fonnte" autocomplete="off">
                        <div class="form-text">Token tersimpan terenkripsi dan tidak ditampilkan lagi setelah disimpan.</div>
                        @error('token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="device_id" class="form-label fw-semibold text-secondary">ID Device GOWA (opsional)</label>
                        <input type="text" class="form-control @error('device_id') is-invalid @enderror" id="device_id" name="device_id" maxlength="100" value="{{ old('device_id') }}" placeholder="Kosongkan untuk dibuat otomatis">
                        @error('device_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Device</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
