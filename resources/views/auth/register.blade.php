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
                <p class="login-box-msg text-muted mb-4 small">Daftar akun Wali untuk mendaftarkan anak Anda — akun dan pendaftaran anak dibuat sekaligus</p>

                <form action="{{ route('guardian.register.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Nama Lengkap Anda</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Nama lengkap" required autocomplete="name">
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label small fw-semibold">Nomor HP (untuk masuk)</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror @error('phone_normalized') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required autocomplete="tel">
                        @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('phone_normalized')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="child_name" class="form-label small fw-semibold">Nama Lengkap Anak</label>
                        <input type="text" class="form-control @error('child_name') is-invalid @enderror" id="child_name" name="child_name" value="{{ old('child_name') }}" placeholder="Nama anak yang didaftarkan" required>
                        @error('child_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="guardian_relation" class="form-label small fw-semibold">Tipe Ortu — Hubungan Anda dengan Anak</label>
                        <select class="form-select @error('guardian_relation') is-invalid @enderror" id="guardian_relation" name="guardian_relation" required>
                            <option value="">Pilih hubungan...</option>
                            @foreach(\App\Models\Application::GUARDIAN_RELATIONS as $key => $label)
                                <option value="{{ $key }}" {{ old('guardian_relation') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('guardian_relation')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="school_unit_id" class="form-label small fw-semibold">Unit Sekolah</label>
                        <select class="form-select @error('school_unit_id') is-invalid @enderror" id="school_unit_id" name="school_unit_id" required>
                            <option value="">Pilih unit...</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('school_unit_id', $selectedUnit?->id) === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->level }} &middot; {{ $unit->program?->name }})</option>
                            @endforeach
                        </select>
                        @error('school_unit_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="admission_wave_id" class="form-label small fw-semibold">Gelombang yang Dibuka</label>
                        <select class="form-select @error('admission_wave_id') is-invalid @enderror" id="admission_wave_id" name="admission_wave_id" required>
                            <option value="">Pilih gelombang...</option>
                            @foreach($waves as $wave)
                                <option value="{{ $wave->id }}" {{ (string) old('admission_wave_id', $selectedWave?->id) === (string) $wave->id ? 'selected' : '' }}>{{ $wave->name }} ({{ $wave->academicYear?->name }}) — s.d. {{ $wave->closes_at?->format('d M Y') }}</option>
                            @endforeach
                        </select>
                        @error('admission_wave_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text text-danger d-none" id="wave-empty-note">Unit ini belum memiliki gelombang yang sedang dibuka.</div>
                    </div>

                    <div class="mb-3">
                        <label for="admission_track_id" class="form-label small fw-semibold">Jalur Pendaftaran</label>
                        <select class="form-select @error('admission_track_id') is-invalid @enderror" id="admission_track_id" name="admission_track_id" required>
                            <option value="">Pilih jalur...</option>
                            @if($selectedWave !== null)
                                @foreach($selectedWave->admissionTracks as $track)
                                    <option value="{{ $track->id }}" {{ (string) old('admission_track_id') === (string) $track->id ? 'selected' : '' }}>{{ $track->name }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('admission_track_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3 {{ $selectedUnit !== null && $selectedUnit->majors->isNotEmpty() ? '' : 'd-none' }}" id="major-wrap">
                        <label for="major_id" class="form-label small fw-semibold">Jurusan</label>
                        <select class="form-select @error('major_id') is-invalid @enderror" id="major_id" name="major_id">
                            <option value="">Tanpa jurusan / belum memilih</option>
                            @if($selectedUnit !== null)
                                @foreach($selectedUnit->majors as $major)
                                    <option value="{{ $major->id }}" {{ (string) old('major_id') === (string) $major->id ? 'selected' : '' }}>{{ $major->name }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('major_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold">Kata Sandi</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Minimal 8 karakter" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label small fw-semibold">Ulangi Kata Sandi</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Ulangi kata sandi" required autocomplete="new-password">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Daftar &amp; Mulai Pendaftaran</button>
                    </div>
                </form>

                <script>
                    // Client-side cascade for the single registration
                    // form: changing the unit refills the wave, track
                    // and major selects from the embedded data, so no
                    // typed field is ever lost to a page reload.
                    (function () {
                        const cascade = @json($cascade);
                        const unitSel = document.getElementById('school_unit_id');
                        const waveSel = document.getElementById('admission_wave_id');
                        const trackSel = document.getElementById('admission_track_id');
                        const majorSel = document.getElementById('major_id');
                        const majorWrap = document.getElementById('major-wrap');
                        const waveEmptyNote = document.getElementById('wave-empty-note');

                        const fill = (sel, items, placeholder, labelOf) => {
                            sel.innerHTML = '';
                            const first = document.createElement('option');
                            first.value = '';
                            first.textContent = placeholder;
                            sel.appendChild(first);
                            items.forEach((item) => {
                                const opt = document.createElement('option');
                                opt.value = item.id;
                                opt.textContent = labelOf(item);
                                sel.appendChild(opt);
                            });
                        };

                        const currentUnit = () => cascade.find((u) => String(u.id) === unitSel.value) || null;

                        const fillTracks = () => {
                            const unit = currentUnit();
                            const wave = unit ? unit.waves.find((w) => String(w.id) === waveSel.value) : null;
                            fill(trackSel, wave ? wave.tracks : [], 'Pilih jalur...', (t) => t.name);
                        };

                        unitSel.addEventListener('change', () => {
                            const unit = currentUnit();
                            fill(waveSel, unit ? unit.waves : [], 'Pilih gelombang...', (w) => w.label);
                            waveEmptyNote.classList.toggle('d-none', !(unit && unit.waves.length === 0));
                            if (unit && unit.waves.length === 1) {
                                waveSel.value = String(unit.waves[0].id);
                            }
                            fillTracks();
                            fill(majorSel, unit ? unit.majors : [], 'Tanpa jurusan / belum memilih', (m) => m.name);
                            majorWrap.classList.toggle('d-none', !(unit && unit.majors.length > 0));
                        });

                        waveSel.addEventListener('change', fillTracks);
                    })();
                </script>

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
