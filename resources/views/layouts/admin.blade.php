<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin Dashboard') | Laravel AdminLTE 4</title>

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bs-body-bg);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Premium Micro-animations & Styles */
        .nav-link {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 8px;
            margin: 2px 8px;
        }

        .nav-link:hover {
            transform: translateX(4px);
        }

        .sidebar-brand {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: white !important;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
        }

        /* Color mode toggle styling */
        .color-modes {
            cursor: pointer;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 4px;
        }
        [data-bs-theme="dark"] ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Custom DataTable Styles for Premium Feel */
        .dataTables_wrapper .dataTables_length select {
            border-radius: 8px;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
            border-color: rgba(0, 0, 0, 0.1);
        }
        [data-bs-theme="dark"] .dataTables_wrapper .dataTables_length select {
            border-color: rgba(255, 255, 255, 0.15);
            background-color: var(--bs-body-bg);
            color: var(--bs-body-color);
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 20px;
            padding: 0.375rem 1rem;
            border-color: rgba(0, 0, 0, 0.1);
            background-color: rgba(0, 0, 0, 0.02);
        }
        [data-bs-theme="dark"] .dataTables_wrapper .dataTables_filter input {
            border-color: rgba(255, 255, 255, 0.15);
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--bs-body-color);
        }
        .dataTables_wrapper .dataTables_info {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.page-item .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: none;
            font-size: 0.875rem;
            color: var(--bs-body-color);
            background-color: transparent;
            transition: all 0.2s;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.page-item.active .page-link {
            background-color: #4f46e5 !important;
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%) !important;
            color: white !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.page-item:hover .page-link {
            background-color: rgba(0, 0, 0, 0.05);
        }
        [data-bs-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button.page-item:hover .page-link {
            background-color: rgba(255, 255, 255, 0.05);
        }
        table.dataTable {
            border-collapse: collapse !important;
            margin-top: 15px !important;
            margin-bottom: 15px !important;
        }
        table.dataTable thead th {
            border-bottom: 2px solid rgba(0, 0, 0, 0.05) !important;
        }
        [data-bs-theme="dark"] table.dataTable thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.08) !important;
        }
        table.dataTable td {
            border-bottom: 1px solid rgba(0, 0, 0, 0.03) !important;
        }
        [data-bs-theme="dark"] table.dataTable td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
    </style>
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
                        <a href="/admin" class="nav-link fs-6 fw-medium">Home</a>
                    </li>
                </ul>

                <!-- Right navbar links -->
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Color Mode Selector -->
                    <li class="nav-item dropdown me-2">
                        <button class="btn btn-link nav-link px-0 text-decoration-none dropdown-toggle d-flex align-items-center"
                                id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static">
                            <i class="bi bi-sun-fill my-1 theme-icon-active" id="active-theme-icon"></i>
                            <span class="d-lg-none ms-2" id="bd-theme-text">Toggle theme</span>
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
                            <span class="dropdown-item dropdown-header fw-bold">3 Notifications</span>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-envelope me-2 text-primary"></i> 1 new message
                                <span class="float-end text-muted text-sm">3 mins</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-people me-2 text-success"></i> 2 user registrations
                                <span class="float-end text-muted text-sm">12 hours</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item dropdown-footer text-center">See All Notifications</a>
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
                                            <a href="/admin" class="btn btn-light btn-sm w-100 border">Home</a>
                                        </div>
                                        <div class="col-6">
                                            <a href="{{ route('logout') }}" class="btn btn-danger btn-sm w-100">Logout</a>
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
                    <span class="brand-text fw-bold">Laravel Admin</span>
                </a>
            </div>
 
            <!-- Sidebar Menu -->
            <div class="sidebar-wrapper py-3" data-simplebar>
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                        
                        @can('view-dashboard')
                        <li class="nav-header text-uppercase fs-7 fw-bold px-3 py-2 text-muted">Core</li>
                        
                        <li class="nav-item">
                            <a href="/admin" class="nav-link {{ request()->is('admin') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-speedometer2 me-2"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        @endcan
 
                        @canany(['user-list', 'role-list', 'permission-list', 'manage-settings'])
                        <li class="nav-header text-uppercase fs-7 fw-bold px-3 py-2 text-muted">Management</li>
                        @endcanany
 
                        @can('user-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->is('admin/users*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-people me-2"></i>
                                <p>Users</p>
                            </a>
                        </li>
                        @endcan
 
                        @can('role-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->is('admin/roles*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-shield-lock me-2"></i>
                                <p>Roles</p>
                            </a>
                        </li>
                        @endcan
 
                        @can('permission-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.permissions.index') }}" class="nav-link {{ request()->is('admin/permissions*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-key me-2"></i>
                                <p>Permissions</p>
                            </a>
                        </li>
                        @endcan
 
                        @can('activity-log-list')
                        <li class="nav-item">
                            <a href="{{ route('admin.activity-logs.index') }}" class="nav-link {{ request()->is('admin/activity-logs*') ? 'active bg-primary text-white' : 'text-body' }}">
                                <i class="nav-icon bi bi-journal-text me-2"></i>
                                <p>Activity Logs</p>
                            </a>
                        </li>
                        @endcan

                        @can('manage-settings')
                        <li class="nav-item">
                            <a href="#" class="nav-link text-body">
                                <i class="nav-icon bi bi-gear me-2"></i>
                                <p>Settings</p>
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
                                    <li class="breadcrumb-item"><a href="/admin" class="text-decoration-none">Home</a></li>
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
                <strong>Copyright &copy; 2026 <a href="/admin" class="text-decoration-none">Laravel Admin</a>.</strong> All rights reserved.
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
