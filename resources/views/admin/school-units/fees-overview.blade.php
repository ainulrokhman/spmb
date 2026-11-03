@extends('layouts.admin')

@section('title', 'Biaya per Unit')
@section('page-title', 'Biaya per Unit')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Biaya per Unit</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">Pengaturan Biaya per Unit</h5>
        <p class="text-muted small mb-0">Satu pintu untuk mengatur biaya setiap unit: mode komponen, nominal, dan tingkatnya. Unit bertanda peringatan memiliki komponen aktif yang belum memiliki nominal.</p>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Unit Sekolah</th>
                        <th class="text-secondary fw-semibold">Komponen Aktif</th>
                        <th class="text-secondary fw-semibold">Kelengkapan Nominal</th>
                        <th style="width: 170px;" class="text-secondary fw-semibold text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($units as $row)
                        <tr>
                            <td>
                                <span class="fw-semibold text-body d-block">{{ $row['unit']->name }}</span>
                                <span class="text-muted small">{{ $row['unit']->level }} &middot; {{ $row['unit']->program?->name }}</span>
                            </td>
                            <td>{{ $row['active'] }} komponen</td>
                            <td>
                                @if($row['active'] === 0)
                                    <span class="text-muted">Belum ada komponen aktif</span>
                                @elseif($row['warnings'] > 0)
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> {{ $row['warnings'] }} komponen belum ada nominal</span>
                                @else
                                    <span class="badge bg-success"><i class="bi bi-check-lg"></i> Lengkap</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.school-units.fees.edit', $row['unit']->id) }}" class="btn btn-sm btn-light border fw-medium">
                                    <i class="bi bi-cash-stack text-success"></i> Atur Biaya
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted text-center py-4">Tidak ada unit yang dapat Anda kelola.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
