@extends('layouts.admin')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor SPMB')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Dasbor</li>
@endsection

@section('content')
@php($sc = $summary['status_counts'])
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h5 class="mb-0 fw-bold text-body">Ringkasan Penerimaan Murid Baru</h5>
        <small class="text-muted">Tahun Ajaran {{ $summary['academic_year']?->name ?? '—' }} (aktif) &middot; sesuai unit tugas Anda</small>
    </div>
    @can('application-list')
        <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart"></i> Laporan Lengkap</a>
    @endcan
</div>

<!-- Row: SPMB headline stats -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Total Pendaftar</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $summary['total_applicants'] }}</h3>
                    <small class="text-muted">Semua status pendaftaran</small>
                </div>
                <div class="text-primary"><i class="bi bi-people fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Menunggu Verifikasi</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $sc['submitted'] }}</h3>
                    <small class="text-muted">Berkas terkirim, belum diputuskan</small>
                </div>
                <div class="text-warning"><i class="bi bi-hourglass-split fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Lulus</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $sc['passed'] + $sc['re_registered'] }}</h3>
                    <small class="text-muted">Termasuk yang sudah daftar ulang</small>
                </div>
                <div class="text-success"><i class="bi bi-patch-check fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Sudah Daftar Ulang</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $sc['re_registered'] }}</h3>
                    <small class="text-muted">Tagihan daftar ulang lunas</small>
                </div>
                <div class="text-info"><i class="bi bi-mortarboard fs-3"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Status breakdown -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Pendaftar per Status</h5>
                <small class="text-muted">Seluruh tahap alur pendaftaran</small>
            </div>
            <div class="card-body px-4 pb-4">
                <table class="table table-sm mb-0">
                    @foreach(\App\Models\Application::STATUSES as $key => $label)
                        <tr>
                            <td class="text-muted">{{ $label }}</td>
                            <td class="text-end fw-semibold">{{ $sc[$key] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>

    <!-- Recent submitted applications -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Pendaftaran Terkirim Terbaru</h5>
                <small class="text-muted">Lima pendaftaran terakhir yang sudah memiliki nomor</small>
            </div>
            <div class="card-body px-4 pb-4">
                @if($summary['recent_applications']->isEmpty())
                    <p class="text-muted small mb-0">Belum ada pendaftaran yang terkirim.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-secondary fw-semibold">Nomor</th>
                                    <th class="text-secondary fw-semibold">Nama Anak</th>
                                    <th class="text-secondary fw-semibold">Unit</th>
                                    <th class="text-secondary fw-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summary['recent_applications'] as $recent)
                                    <tr>
                                        <td>{{ $recent->registration_number }}</td>
                                        <td class="fw-semibold">{{ $recent->child_name }}</td>
                                        <td>{{ $recent->schoolUnit?->name }}</td>
                                        <td>{{ $recent->statusLabel() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Per-unit reception -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body">Rekap per Unit</h5>
        <small class="text-muted">Pendaftar, daftar ulang, dan pembayaran yang sudah lunas</small>
    </div>
    <div class="card-body px-4 pb-4">
        @if($summary['units'] === [])
            <p class="text-muted small mb-0">Belum ada unit sekolah.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Unit</th>
                            <th class="text-secondary fw-semibold text-end">Pendaftar</th>
                            <th class="text-secondary fw-semibold text-end">Daftar Ulang</th>
                            <th class="text-secondary fw-semibold text-end">Formulir Lunas</th>
                            <th class="text-secondary fw-semibold text-end">Tagihan Lunas</th>
                            <th class="text-secondary fw-semibold text-end">Total Penerimaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summary['units'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['unit']->name }}</td>
                                <td class="text-end">{{ $row['applicants'] }}</td>
                                <td class="text-end">{{ $row['re_registered'] }}</td>
                                <td class="text-end">Rp {{ number_format($row['form_fees_received'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row['invoices_received'], 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format($row['total_received'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Row: system counts -->
<div class="row g-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Total Pengguna</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $usersCount }}</h3>
                    <small class="text-muted">Akun terdaftar</small>
                </div>
                <div class="text-primary"><i class="bi bi-person-gear fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Total Peran</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $rolesCount }}</h3>
                    <small class="text-muted">Tingkatan keamanan</small>
                </div>
                <div class="text-success"><i class="bi bi-shield-lock fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Hak Akses</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $permissionsCount }}</h3>
                    <small class="text-muted">Aksi sistem</small>
                </div>
                <div class="text-warning"><i class="bi bi-key fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body px-4 py-3">
                <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-2">Anggota Terbaru</span>
                @forelse($recentUsers->take(3) as $user)
                    <div class="small mb-1"><span class="fw-semibold">{{ $user->name }}</span> <span class="text-muted">— {{ $user->created_at->diffForHumans() }}</span></div>
                @empty
                    <small class="text-muted">Belum ada anggota terbaru</small>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
