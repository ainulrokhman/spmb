@extends('layouts.admin')

@section('title', 'Dasbor')
@section('page-title', 'Overview Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Dasbor</li>
@endsection

@section('content')
<!-- Row: Info Boxes / Stats -->
<div class="row g-4 mb-4">
    <!-- Info Box 1: Users -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Total Pengguna</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $usersCount }}</h3>
                    @can('user-list')
                    <a href="{{ route('admin.users.index') }}" class="text-primary small text-decoration-none fw-medium d-inline-flex align-items-center mt-2">
                        Manage Users <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                    @else
                    <small class="text-muted">Akun terdaftar</small>
                    @endcan
                </div>
                <div class="text-primary">
                    <i class="bi bi-people fs-3"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Box 2: Roles -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Total Peran</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $rolesCount }}</h3>
                    @can('role-list')
                    <a href="{{ route('admin.roles.index') }}" class="text-success small text-decoration-none fw-medium d-inline-flex align-items-center mt-2">
                        Manage Roles <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                    @else
                    <small class="text-muted">Tingkatan keamanan</small>
                    @endcan
                </div>
                <div class="text-success">
                    <i class="bi bi-shield-lock fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Box 3: Permissions -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Hak Akses</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $permissionsCount }}</h3>
                    @can('permission-list')
                    <a href="{{ route('admin.permissions.index') }}" class="text-warning small text-decoration-none fw-medium d-inline-flex align-items-center mt-2">
                        Configure <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                    @else
                    <small class="text-muted">Aksi sistem</small>
                    @endcan
                </div>
                <div class="text-warning">
                    <i class="bi bi-key fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Box 4: Current Session -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Peran Anda</span>
                    <h3 class="mb-0 fw-bold text-info" style="font-size: 1.5rem;">
                        {{ auth()->user()->roles->first()?->name ?? 'No Role' }}
                    </h3>
                    <small class="text-muted">Logged in as {{ auth()->user()->email }}</small>
                </div>
                <div class="text-info">
                    <i class="bi bi-person-badge fs-3"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row: Detailed Stats & Tables -->
<div class="row g-4">
    <!-- Sales Overview / Chart Mockup -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between pt-4 px-4">
                <div>
                    <h5 class="mb-0 fw-bold text-body">Kinerja Penjualan</h5>
                    <small class="text-muted">Statistik tagihan dan pendapatan bulanan</small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        2026
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">2026</a></li>
                        <li><a class="dropdown-item" href="#">2025</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <!-- Premium Progress bars as interactive visual indicators -->
                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold">Penjualan Toko Online</span>
                        <span class="text-body fw-bold">75% ($93k)</span>
                    </div>
                    <div class="progress" >
                        <div class="progress-bar bg-primary" role="progressbar" style="width: 75%;" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold">Penjualan Klien Enterprise / API</span>
                        <span class="text-body fw-bold">45% ($56k)</span>
                    </div>
                    <div class="progress" >
                        <div class="progress-bar bg-success" role="progressbar" style="width: 45%;" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold">Reseller & Afiliasi</span>
                        <span class="text-body fw-bold">20% ($25k)</span>
                    </div>
                    <div class="progress" >
                        <div class="progress-bar bg-info" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-body-secondary rounded-3 border-0">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill text-primary fs-4 me-3"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">Proyeksi Pertumbuhan</h6>
                            <p class="text-muted small mb-0">Berdasarkan perpanjangan langganan aktif, kami memperkirakan kenaikan 15% pada tagihan penggunaan API bulan depan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Registrations -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="mb-0 fw-bold text-body">Anggota Terbaru</h5>
                <small class="text-muted">Pengguna baru yang terdaftar minggu ini</small>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-flex flex-column gap-3">
                    
                    @forelse($recentUsers as $user)
                    @php
                        $initials = collect(explode(' ', $user->name))
                            ->map(fn($segment) => mb_substr($segment, 0, 1))
                            ->take(2)
                            ->join('');
                        $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                        // Consistent color selection based on ID
                        $color = $colors[$user->id % count($colors)];
                    @endphp
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            @if(isset($user->avatar) && $user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" alt="Avatar">
                            @else
                                <div>
                                    {{ strtoupper($initials) }}
                                </div>
                            @endif
                            <div class="ms-3">
                                <h6 class="mb-0 fw-bold text-body">{{ $user->name }}</h6>
                                <small class="text-muted">{{ $user->email }}</small>
                            </div>
                        </div>
                        <span class="badge bg-light text-secondary border">{{ $user->created_at->diffForHumans() }}</span>
                    </div>
                    @empty
                    <div class="text-center py-3">
                        <small class="text-muted">Belum ada anggota terbaru</small>
                    </div>
                    @endforelse

                </div>
                @can('user-list')
                <div class="text-center mt-4">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary w-100 py-2 fw-medium">
                        Lihat Semua Pengguna
                    </a>
                </div>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
