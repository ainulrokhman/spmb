@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Overview Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')
<!-- Row: Info Boxes / Stats -->
<div class="row g-4 mb-4">
    <!-- Info Box 1: Users -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="position-absolute start-0 top-0 bottom-0 bg-primary" style="width: 4px;"></div>
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Total Users</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $usersCount }}</h3>
                    @can('user-list')
                    <a href="{{ route('admin.users.index') }}" class="text-primary small text-decoration-none fw-medium d-inline-flex align-items-center mt-2">
                        Manage Users <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                    @else
                    <small class="text-muted">Registered accounts</small>
                    @endcan
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                    <i class="bi bi-people fs-3"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Box 2: Roles -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="position-absolute start-0 top-0 bottom-0 bg-success" style="width: 4px;"></div>
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Total Roles</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $rolesCount }}</h3>
                    @can('role-list')
                    <a href="{{ route('admin.roles.index') }}" class="text-success small text-decoration-none fw-medium d-inline-flex align-items-center mt-2">
                        Manage Roles <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                    @else
                    <small class="text-muted">Security levels</small>
                    @endcan
                </div>
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-3">
                    <i class="bi bi-shield-lock fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Box 3: Permissions -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="position-absolute start-0 top-0 bottom-0 bg-warning" style="width: 4px;"></div>
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Permissions</span>
                    <h3 class="mb-0 fw-bold text-body">{{ $permissionsCount }}</h3>
                    @can('permission-list')
                    <a href="{{ route('admin.permissions.index') }}" class="text-warning small text-decoration-none fw-medium d-inline-flex align-items-center mt-2">
                        Configure <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                    @else
                    <small class="text-muted">System actions</small>
                    @endcan
                </div>
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                    <i class="bi bi-key fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Box 4: Current Session -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
            <div class="position-absolute start-0 top-0 bottom-0 bg-info" style="width: 4px;"></div>
            <div class="card-body d-flex align-items-center justify-content-between p-4">
                <div>
                    <span class="text-muted text-uppercase fs-7 fw-bold d-block mb-1">Your Role</span>
                    <h3 class="mb-0 fw-bold text-info" style="font-size: 1.5rem;">
                        {{ auth()->user()->roles->first()?->name ?? 'No Role' }}
                    </h3>
                    <small class="text-muted">Logged in as {{ auth()->user()->email }}</small>
                </div>
                <div class="bg-info bg-opacity-10 text-info rounded-3 p-3">
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
                    <h5 class="mb-0 fw-bold text-body">Sales Performance</h5>
                    <small class="text-muted">Monthly billing and income statistics</small>
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
                        <span class="text-secondary fw-semibold">Online Store Sales</span>
                        <span class="text-body fw-bold">75% ($93k)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary rounded-pill" role="progressbar" style="width: 75%;" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold">Enterprise / API Client Sales</span>
                        <span class="text-body fw-bold">45% ($56k)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: 45%;" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold">Reseller & Affiliates</span>
                        <span class="text-body fw-bold">20% ($25k)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info rounded-pill" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-body-secondary rounded-3 border-0">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill text-primary fs-4 me-3"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">Projected Growth</h6>
                            <p class="text-muted small mb-0">Based on active subscription renewals, we anticipate a 15% increase in next month's API usage billing.</p>
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
                <h5 class="mb-0 fw-bold text-body">Recent Members</h5>
                <small class="text-muted">Newly registered users this week</small>
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
                                <div class="bg-{{ $color }} bg-opacity-10 text-{{ $color }} rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
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
                        <small class="text-muted">No recent members</small>
                    </div>
                    @endforelse

                </div>
                @can('user-list')
                <div class="text-center mt-4">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary w-100 py-2 fw-medium" style="border-radius: 8px;">
                        View All Users
                    </a>
                </div>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
