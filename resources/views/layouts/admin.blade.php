<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dasbor Admin') | SPMB</title>

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        
        <!-- Header Navbar -->
        <nav class="app-header navbar navbar-expand bg-body border-bottom">
            <div class="container-fluid">
                <!-- Left navbar links -->
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list fs-4"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-inline-block">
                        <a href="/admin" class="nav-link fs-6 fw-medium">Beranda</a>
                    </li>
                </ul>

                <!-- Right navbar links -->
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Color Mode Selector -->
                    <li class="nav-item dropdown me-2">
                        <button class="btn btn-link nav-link px-0 text-decoration-none dropdown-toggle d-flex align-items-center"
                                id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static">
                            <i class="bi bi-sun-fill my-1 theme-icon-active" id="active-theme-icon"></i>
                            <span class="d-lg-none ms-2" id="bd-theme-text">Ganti tema</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bd-theme-text">
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
                                    <i class="bi bi-sun-fill me-2 opacity-50"></i>
                                    Light
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
                                    <i class="bi bi-moon-stars-fill me-2 opacity-50"></i>
                                    Dark
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
                                    <i class="bi bi-circle-half me-2 opacity-50"></i>
                                    Auto
                                </button>
                            </li>
                        </ul>
                    </li>

                    <!-- Notifications Dropdown -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link px-2 position-relative" data-bs-toggle="dropdown" href="#">
                            <i class="bi bi-bell fs-5"></i>
                            <span class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.65rem;">
                                3
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow border-0">
                            <span class="dropdown-item dropdown-header fw-bold">3 Notifikasi</span>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-envelope me-2 text-primary"></i> 1 pesan baru
                                <span class="float-end text-muted text-sm">3 mnt</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-people me-2 text-success"></i> 2 pendaftaran pengguna
                                <span class="float-end text-muted text-sm">12 jam</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item dropdown-footer text-center">Lihat Semua Notifikasi</a>
                        </div>
                    </li>

                    <!-- User Menu Dropdown -->
                    <li class="nav-item dropdown user-menu">
                        @php
                            $currentUser = auth()->user();
                            $menuInitials = collect(explode(' ', $currentUser->name ?? 'U'))
                                ->map(fn($segment) => mb_substr($segment, 0, 1))
                                ->take(2)
                                ->join('');
                        @endphp
                        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                            @if($currentUser && $currentUser->avatar)
                                <img src="{{ asset('storage/' . $currentUser->avatar) }}" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;" alt="Avatar">
                            @else
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold me-2" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                    {{ strtoupper($menuInitials) }}
                                </div>
                            @endif
                            <span class="d-none d-md-inline fw-semibold text-body">{{ $currentUser->name ?? 'User' }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow border-0">
                            <!-- User image -->
                            <li class="user-header bg-primary text-white text-center py-4 rounded-top">
                                @if($currentUser && $currentUser->avatar)
                                    <img src="{{ asset('storage/' . $currentUser->avatar) }}" class="rounded-circle shadow mb-2 border border-white border-2" style="width: 64px; height: 64px; object-fit: cover;" alt="Avatar">
                                @else
                                    <div class="bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center fw-bold mb-2 shadow" style="width: 64px; height: 64px; font-size: 1.5rem;">
                                        {{ strtoupper($menuInitials) }}
                                    </div>
                                @endif
                                <p class="mb-0 fw-bold">
                                    {{ $currentUser->name ?? 'User' }}
                                </p>
                                <small>Role: {{ $currentUser->roles->first()?->name ?? 'None' }}</small>
                            </li>
                            <!-- Menu Body -->
                            <li class="user-body p-3">
                                <div class="d-flex flex-column gap-2 text-center">
                                    <a href="{{ route('admin.profile.edit') }}" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="bi bi-person-gear me-1"></i> My Profile
                                    </a>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <a href="/admin" class="btn btn-light btn-sm w-100 border">Beranda</a>
                                        </div>
                                        <div class="col-6">
                                            <a href="{{ route('logout') }}" class="btn btn-danger btn-sm w-100">Keluar</a>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
 
        <!-- Main Sidebar -->
        <aside class="app-sidebar bg-body-secondary shadow border-end">
            <!-- Sidebar Brand -->
            <div class="sidebar-brand d-flex align-items-center px-4 py-3 justify-content-between border-bottom">
                <a href="/admin" class="brand-link text-decoration-none d-flex align-items-center">
                    <i class="bi bi-cpu-fill fs-4 me-2"></i>
                    <span class="brand-text fw-bold">SPMB</span>
                </a>
            </div>
 
            <!-- Sidebar Menu -->
            <div class="sidebar-wrapper py-3" data-simplebar>
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                        @can('view-dashboard')
                        <li class="nav-item">
                            <a href="/admin" class="nav-link {{ request()->is('admin') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-speedometer2 me-2"></i>
                                <p>Dasbor</p>
                            </a>
                        </li>
                        @endcan

                        @canany(['academic-year-list', 'school-unit-list', 'admission-track-list', 'major-list', 'admission-wave-list'])
                        <li class="nav-header text-uppercase fs-7 fw-bold px-3 py-2 text-muted">Data Master</li>
                        @endcanany

                        @can('academic-year-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.academic-years.index') }}" class="nav-link {{ request()->is('admin/academic-years*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-calendar-range me-2"></i>
                                <p>Tahun Ajaran</p>
                            </a>
                        </li>
                        @endcan

                        @can('school-unit-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.school-units.index') }}" class="nav-link {{ request()->is('admin/school-units*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-building me-2"></i>
                                <p>Unit Sekolah</p>
                            </a>
                        </li>
                        @endcan

                        @can('admission-track-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.admission-tracks.index') }}" class="nav-link {{ request()->is('admin/admission-tracks*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-signpost-split me-2"></i>
                                <p>Jalur Pendaftaran</p>
                            </a>
                        </li>
                        @endcan

                        @can('major-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.majors.index') }}" class="nav-link {{ request()->is('admin/majors*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-diagram-3 me-2"></i>
                                <p>Jurusan</p>
                            </a>
                        </li>
                        @endcan

                        @can('admission-wave-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.admission-waves.index') }}" class="nav-link {{ request()->is('admin/admission-waves*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-water me-2"></i>
                                <p>Gelombang Pendaftaran</p>
                            </a>
                        </li>
                        @endcan

                        @canany(['document-type-list', 'form-field-list'])
                        <li class="nav-header text-uppercase fs-7 fw-bold px-3 py-2 text-muted">Berkas & Formulir</li>
                        @endcanany

                        @can('document-type-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.document-types.index') }}" class="nav-link {{ request()->is('admin/document-types*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-file-earmark-text me-2"></i>
                                <p>Jenis Berkas</p>
                            </a>
                        </li>
                        @endcan

                        @can('form-field-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.form-fields.index') }}" class="nav-link {{ request()->is('admin/form-fields*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-ui-checks me-2"></i>
                                <p>Bidang Formulir</p>
                            </a>
                        </li>
                        @endcan

                        @canany(['fee-component-list', 'fee-amount-list'])
                        <li class="nav-header text-uppercase fs-7 fw-bold px-3 py-2 text-muted">Biaya</li>
                        @endcanany

                        @can('fee-component-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.fee-components.index') }}" class="nav-link {{ request()->is('admin/fee-components*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-tags me-2"></i>
                                <p>Komponen Biaya</p>
                            </a>
                        </li>
                        @endcan

                        @can('fee-amount-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.fee-amounts.index') }}" class="nav-link {{ request()->is('admin/fee-amounts*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-cash-stack me-2"></i>
                                <p>Nominal Biaya</p>
                            </a>
                        </li>
                        @endcan

                        @canany(['user-list', 'role-list', 'permission-list'])
                        <li class="nav-header text-uppercase fs-7 fw-bold px-3 py-2 text-muted">Pengguna & Akses</li>
                        @endcanany

                        @can('user-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->is('admin/users*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-people me-2"></i>
                                <p>Pengguna</p>
                            </a>
                        </li>
                        @endcan

                        @can('role-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->is('admin/roles*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-shield-lock me-2"></i>
                                <p>Peran</p>
                            </a>
                        </li>
                        @endcan

                        @can('permission-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.permissions.index') }}" class="nav-link {{ request()->is('admin/permissions*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-key me-2"></i>
                                <p>Hak Akses</p>
                            </a>
                        </li>
                        @endcan

                        @canany(['activity-log-list', 'manage-settings'])
                        <li class="nav-header text-uppercase fs-7 fw-bold px-3 py-2 text-muted">Sistem</li>
                        @endcanany

                        @can('activity-log-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.activity-logs.index') }}" class="nav-link {{ request()->is('admin/activity-logs*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-journal-text me-2"></i>
                                <p>Log Aktivitas</p>
                            </a>
                        </li>
                        @endcan

                        @can('manage-settings')
                        <li class="nav-item">
                            <a href="#" class="nav-link text-body">
                                <i class="nav-icon bi bi-gear me-2"></i>
                                <p>Pengaturan</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="app-main py-4">
            <!-- Content Header -->
            <div class="app-content-header mb-4">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <h3 class="mb-0 fw-bold">@yield('page-title')</h3>
                        </div>
                        <div class="col-sm-6">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb float-sm-end mb-0">
                                    <li class="breadcrumb-item"><a href="/admin" class="text-decoration-none">Beranda</a></li>
                                    @yield('breadcrumb')
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="app-content">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="app-footer border-top bg-body py-3 text-center text-sm-start">
            <div class="container-fluid">
                <div class="float-end d-none d-sm-inline">
                    AdminLTE v4
                </div>
                <strong>Hak Cipta &copy; 2026 <a href="/admin" class="text-decoration-none">SPMB</a>.</strong> Seluruh hak cipta dilindungi.
            </div>
        </footer>

    </div>

    <!-- Theme Selection Javascript -->
    <script>
        const getStoredTheme = () => localStorage.getItem('theme')
        const setStoredTheme = theme => localStorage.setItem('theme', theme)

        const getPreferredTheme = () => {
            const storedTheme = getStoredTheme()
            if (storedTheme) {
                return storedTheme
            }
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
        }

        const setTheme = theme => {
            if (theme === 'auto') {
                document.documentElement.setAttribute('data-bs-theme', window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            } else {
                document.documentElement.setAttribute('data-bs-theme', theme)
            }
        }

        setTheme(getPreferredTheme())

        const showActiveTheme = (theme, focus = false) => {
            const themeToggler = document.querySelector('#bd-theme')
            if (!themeToggler) return

            const themeIconActive = document.querySelector('#active-theme-icon')
            const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`)
            
            document.querySelectorAll('[data-bs-theme-value]').forEach(element => {
                element.classList.remove('active')
                element.setAttribute('aria-pressed', 'false')
            })

            btnToActive.classList.add('active')
            btnToActive.setAttribute('aria-pressed', 'true')

            // Update active icon
            themeIconActive.className = ''
            if (theme === 'light') {
                themeIconActive.className = 'bi bi-sun-fill my-1 theme-icon-active'
            } else if (theme === 'dark') {
                themeIconActive.className = 'bi bi-moon-stars-fill my-1 theme-icon-active'
            } else {
                themeIconActive.className = 'bi bi-circle-half my-1 theme-icon-active'
            }
        }

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const storedTheme = getStoredTheme()
            if (storedTheme !== 'light' && storedTheme !== 'dark') {
                setTheme(getPreferredTheme())
            }
        })

        window.addEventListener('DOMContentLoaded', () => {
            showActiveTheme(getPreferredTheme())
            
            document.querySelectorAll('[data-bs-theme-value]')
                .forEach(toggle => {
                    toggle.addEventListener('click', () => {
                        const theme = toggle.getAttribute('data-bs-theme-value')
                        setStoredTheme(theme)
                        setTheme(theme)
                        showActiveTheme(theme, true)
                    })
                })
        })
    </script>
    @stack('scripts')
</body>
</html>
