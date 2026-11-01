<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Wali | SPMB</title>

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

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
                <span class="d-none d-sm-inline">Tema</span>
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
                <span class="fw-bold text-body">SPMB</span>
            </a>
        </div>

        <div class="card card-outline card-primary shadow-lg border-0">
            <div class="card-body login-card-body p-4">
                <p class="login-box-msg text-muted mb-4 small">Daftar akun Wali untuk mendaftarkan anak Anda</p>

                <form action="{{ route('guardian.register.store') }}" method="POST">
                    @csrf

                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="name"
                                   name="name"
                                   value="{{ old('name') }}"
                                   placeholder="Nama lengkap"
                                   required
                                   autocomplete="name"
                                   autofocus>
                            <label for="name">Nama Lengkap</label>
                        </div>
                        <div class="input-group-text bg-body-tertiary border-start-0 text-muted">
                            <span class="bi bi-person"></span>
                        </div>
                        @error('name')
                            <div class="invalid-feedback d-block w-100 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="text"
                                   class="form-control @error('phone') is-invalid @enderror @error('phone_normalized') is-invalid @enderror"
                                   id="phone"
                                   name="phone"
                                   value="{{ old('phone') }}"
                                   placeholder="08xxxxxxxxxx"
                                   required
                                   autocomplete="tel">
                            <label for="phone">Nomor HP (untuk masuk)</label>
                        </div>
                        <div class="input-group-text bg-body-tertiary border-start-0 text-muted">
                            <span class="bi bi-phone"></span>
                        </div>
                        @error('phone')
                            <div class="invalid-feedback d-block w-100 mt-1">{{ $message }}</div>
                        @enderror
                        @error('phone_normalized')
                            <div class="invalid-feedback d-block w-100 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   id="password"
                                   name="password"
                                   placeholder="Kata Sandi"
                                   required
                                   autocomplete="new-password">
                            <label for="password">Kata Sandi</label>
                        </div>
                        <div class="input-group-text bg-body-tertiary border-start-0 text-muted">
                            <span class="bi bi-lock-fill"></span>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block w-100 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="input-group mb-4">
                        <div class="form-floating">
                            <input type="password"
                                   class="form-control"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   placeholder="Ulangi Kata Sandi"
                                   required
                                   autocomplete="new-password">
                            <label for="password_confirmation">Ulangi Kata Sandi</label>
                        </div>
                        <div class="input-group-text bg-body-tertiary border-start-0 text-muted">
                            <span class="bi bi-lock-fill"></span>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Daftar</button>
                    </div>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a>
                </p>
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

    </script>
</body>
</html>
