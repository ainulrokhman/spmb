<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Area Wali') | SPMB</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="layout-top-nav bg-body-tertiary">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body border-bottom">
            <div class="container">
                <a href="{{ route('admissions.info') }}" class="navbar-brand d-flex align-items-center text-decoration-none">
                    <i class="bi bi-cpu-fill text-primary me-2 fs-4"></i>
                    <span class="fw-bold text-body">SPMB</span>
                </a>

                <ul class="navbar-nav ms-auto align-items-center flex-row gap-2">
                    <li class="nav-item">
                        <a href="{{ route('admissions.info') }}" class="nav-link">Info Pendaftaran</a>
                    </li>
                    @auth
                        @if(auth()->user()->hasRole(\App\Support\SystemAccess::WALI_ROLE))
                            <li class="nav-item">
                                <a href="{{ route('guardian.dashboard') }}" class="nav-link">Dasbor Saya</a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <span class="nav-link text-muted">{{ auth()->user()->name }}</span>
                        </li>
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Keluar</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Masuk</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('guardian.register') }}" class="btn btn-primary btn-sm">Daftar</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </nav>

        <main class="app-main">
            <div class="app-content py-4">
                <div class="container">
                    @if(session('success'))
                        <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div>{{ session('success') }}</div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </main>

        <footer class="app-footer">
            <div class="container text-muted small">
                SPMB — Sistem Penerimaan Murid Baru
            </div>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
