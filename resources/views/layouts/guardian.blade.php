<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Area Wali') | SPMB</title>

    @php($siteName = \App\Models\Setting::get('foundation_name'))
    @php($siteTagline = \App\Models\Setting::get('foundation_tagline'))
    @php($siteLogo = \App\Models\Setting::get('site_logo'))
    <meta name="description" content="Pendaftaran murid baru {{ $siteName ?: 'SPMB' }} secara online: satu akun wali untuk semua anak, pembayaran fleksibel, dan status pendaftaran terpantau. {{ $siteTagline }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="SPMB{{ $siteName ? ' — '.$siteName : '' }}">
    <meta property="og:description" content="Pendaftaran murid baru secara online: satu akun wali untuk semua anak, pembayaran fleksibel, dan status pendaftaran terpantau. {{ $siteTagline }}">
    @if($siteLogo)
        <meta property="og:image" content="{{ asset('storage/'.$siteLogo) }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="layout-top-nav bg-body-tertiary">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body border-bottom">
            <div class="container">
                <a href="{{ route('home') }}" class="navbar-brand d-flex align-items-center text-decoration-none">
                    @if($siteLogo)
                        <img src="{{ asset('storage/'.$siteLogo) }}" alt="Logo {{ $siteName ?: 'SPMB' }}" style="height: 34px;" class="me-2">
                    @else
                        <i class="bi bi-cpu-fill text-primary me-2 fs-4"></i>
                    @endif
                    <span class="fw-bold text-body text-truncate" style="max-width: 48vw;">{{ $siteName ?: 'SPMB' }}</span>
                </a>

                <ul class="navbar-nav ms-auto align-items-center flex-row gap-2">
                    @auth
                        @php($isWali = auth()->user()->hasRole(\App\Support\SystemAccess::WALI_ROLE))
                        @if($isWali)
                            @php($guardianUnread = app(\App\Contracts\GuardianNotificationServiceInterface::class)->visibleUnread(auth()->user()))
                            <li class="nav-item dropdown">
                                <a href="#" class="nav-link position-relative" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifikasi">
                                    <i class="bi bi-bell fs-5"></i>
                                    @if($guardianUnread->isNotEmpty())
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $guardianUnread->count() }}</span>
                                    @endif
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 320px; max-width: 92vw;">
                                    <li><h6 class="dropdown-header">Notifikasi</h6></li>
                                    @forelse($guardianUnread->take(5) as $notification)
                                        <li>
                                            <a class="dropdown-item" href="{{ route('guardian.notifications.show', $notification) }}">
                                                <i class="{{ $notification->data['icon'] ?? 'bi bi-bell' }} text-primary me-1"></i>
                                                <strong>{{ $notification->data['title'] ?? 'Notifikasi' }}</strong>
                                                <span class="d-block small text-muted text-wrap">{{ $notification->data['message'] ?? '' }}</span>
                                            </a>
                                        </li>
                                    @empty
                                        <li><span class="dropdown-item-text text-muted small">Tidak ada notifikasi baru.</span></li>
                                    @endforelse
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="{{ route('guardian.notifications.index') }}">Lihat semua notifikasi</a></li>
                                </ul>
                            </li>
                        @endif
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-1" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5"></i>
                                <span class="text-truncate" style="max-width: 110px;">{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><h6 class="dropdown-header text-truncate" style="max-width: 240px;">{{ auth()->user()->name }}</h6></li>
                                @if($isWali)
                                    <li><a class="dropdown-item" href="{{ route('guardian.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dasbor Saya</a></li>
                                    <li><a class="dropdown-item" href="{{ route('guardian.notifications.index') }}"><i class="bi bi-bell me-2"></i>Semua Notifikasi</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ route('admissions.info') }}"><i class="bi bi-info-circle me-2"></i>Info Pendaftaran</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ route('admissions.info') }}" class="nav-link">Info Pendaftaran</a>
                        </li>
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
