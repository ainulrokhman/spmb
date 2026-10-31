<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Laravel Admin</title>

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .btn-outline-secondary {
            border-color: rgba(0, 0, 0, 0.12);
        }
        [data-bs-theme="dark"] .btn-outline-secondary {
            border-color: rgba(255, 255, 255, 0.15);
            color: #cbd5e1;
        }
        [data-bs-theme="dark"] .btn-outline-secondary:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        /* Style fixes for floating label with input-group */
        .input-group > .form-floating {
            flex: 1 1 auto;
            width: 1%;
        }
        .input-group > .form-floating > .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
    </style>

    <script>
        const STORAGE_KEY = 'theme';

        const getStoredTheme = () => localStorage.getItem(STORAGE_KEY);
        const setStoredTheme = (theme) => localStorage.setItem(STORAGE_KEY, theme);

        const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

        const getPreferredTheme = () => {
            const stored = getStoredTheme();
            if (stored) return stored;
            return prefersDark() ? 'dark' : 'light';
        };

        const setTheme = (theme) => {
            const resolved = theme === 'auto' ? (prefersDark() ? 'dark' : 'light') : theme;
            document.documentElement.setAttribute('data-bs-theme', resolved);
        };

        setTheme(getPreferredTheme());
    </script>
</head>
<body class="login-page bg-body-secondary">

    <!-- Floating Theme Toggle -->
    <div class="position-fixed top-0 end-0 p-3 z-3">
        <div class="dropdown">
            <button class="btn btn-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2 border-0 shadow-sm"
                    id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static">
                <i class="bi bi-circle-half" id="active-theme-icon"></i>
                <span class="d-none d-sm-inline">Theme</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="bd-theme">
                <li>
                    <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="light">
                        <i class="bi bi-sun-fill opacity-50"></i> Light
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="dark">
                        <i class="bi bi-moon-stars-fill opacity-50"></i> Dark
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-theme-value="auto">
                        <i class="bi bi-circle-half opacity-50"></i> Auto
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="login-box">
        <div class="login-logo mb-4">
            <a href="/" class="text-decoration-none d-flex align-items-center justify-content-center">
                <i class="bi bi-cpu-fill text-primary me-2 fs-2"></i>
                <span class="fw-bold text-body">Laravel</span> <span class="text-primary ms-1 fw-semibold">Admin</span>
            </a>
        </div>

        <div class="card card-outline card-primary shadow-lg border-0">
            <div class="card-body login-card-body p-4">
                <p class="login-box-msg text-muted mb-4 small">Sign in to start your session</p>

                @if(session('success'))
                    <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 small" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('login') }}" method="POST" id="loginForm">
                    @csrf
                    
                    <!-- Email Input -->
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   placeholder="name@example.com" 
                                   required 
                                   autocomplete="email" 
                                   autofocus>
                            <label for="email">Email Address</label>
                        </div>
                        <div class="input-group-text bg-body-tertiary border-start-0 text-muted">
                            <span class="bi bi-envelope"></span>
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block w-100 mt-1">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Password Input -->
                    <div class="input-group mb-4">
                        <div class="form-floating">
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Password" 
                                   required 
                                   autocomplete="current-password">
                            <label for="password">Password</label>
                        </div>
                        <div class="input-group-text bg-body-tertiary border-start-0 text-muted">
                            <span class="bi bi-lock-fill"></span>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block w-100 mt-1">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Remember Me & Submit Row -->
                    <div class="row align-items-center mb-3">
                        <div class="col-8">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember" style="cursor: pointer;">
                                <label class="form-check-label text-muted small" for="remember" style="cursor: pointer; user-select: none;">
                                    Remember Me
                                </label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Sign In</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Quick testing accounts option panel -->
                <div class="mt-4 pt-3 border-top">
                    <p class="text-uppercase text-center text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        Quick Testing Accounts
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start d-flex justify-content-between align-items-center px-3 py-2" onclick="quickLogin('superadmin@example.com', 'password')">
                            <span><strong class="text-primary">Super Admin</strong> <span class="text-muted small ms-1">(Full Access)</span></span>
                            <i class="bi bi-arrow-right-short text-muted"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start d-flex justify-content-between align-items-center px-3 py-2" onclick="quickLogin('admin@example.com', 'password')">
                            <span><strong class="text-success">Admin</strong> <span class="text-muted small ms-1">(Users & Roles)</span></span>
                            <i class="bi bi-arrow-right-short text-muted"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start d-flex justify-content-between align-items-center px-3 py-2" onclick="quickLogin('user@example.com', 'password')">
                            <span><strong class="text-info">Regular User</strong> <span class="text-muted small ms-1">(Dashboard Only)</span></span>
                            <i class="bi bi-arrow-right-short text-muted"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const showActiveTheme = (theme) => {
            document.querySelectorAll('[data-bs-theme-value]').forEach((el) => {
                el.classList.remove('active');
                el.setAttribute('aria-pressed', 'false');
            });
            const active = document.querySelector(`[data-bs-theme-value="${theme}"]`);
            if (active) {
                active.classList.add('active');
                active.setAttribute('aria-pressed', 'true');
            }
            const icon = document.querySelector('#active-theme-icon');
            if (icon) {
                icon.className = '';
                if (theme === 'light') {
                    icon.className = 'bi bi-sun-fill';
                } else if (theme === 'dark') {
                    icon.className = 'bi bi-moon-stars-fill';
                } else {
                    icon.className = 'bi bi-circle-half';
                }
            }
        };

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const stored = getStoredTheme();
            if (!stored || stored === 'auto') setTheme(getPreferredTheme());
        });

        document.addEventListener('DOMContentLoaded', () => {
            showActiveTheme(getPreferredTheme());
            document.querySelectorAll('[data-bs-theme-value]').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const theme = toggle.getAttribute('data-bs-theme-value');
                    setStoredTheme(theme);
                    setTheme(theme);
                    showActiveTheme(theme);
                });
            });
        });

        function quickLogin(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            
            const form = document.getElementById('loginForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Logging in...';
            submitBtn.disabled = true;

            setTimeout(() => {
                form.submit();
            }, 300);
        }
    </script>
</body>
</html>
