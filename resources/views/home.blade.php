@extends('layouts.guardian')

@section('title', 'Penerimaan Murid Baru')

@section('content')
{{-- Hero --}}
<div class="text-center py-5">
    @if($branding['logo'])
        <img src="{{ asset('storage/'.$branding['logo']) }}" alt="Logo {{ $branding['name'] ?: 'yayasan' }}" style="height: 96px;" class="mb-3">
    @endif
    <p class="text-primary fw-semibold text-uppercase small mb-2">Penerimaan Murid Baru</p>
    <h1 class="display-5 fw-bold mb-3">{{ $branding['name'] ?: 'SPMB — Sistem Penerimaan Murid Baru' }}</h1>
    <p class="lead text-muted mx-auto" style="max-width: 720px;">
        {{ $branding['tagline'] ?: 'Pendaftaran sekolah dari PAUD sampai MA/SMK dalam satu yayasan — cukup dari HP Anda, tanpa antre, dan statusnya terpantau sampai selesai.' }}
    </p>
    <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
        <a href="{{ route('guardian.register') }}" class="btn btn-primary btn-lg"><i class="bi bi-pencil-square"></i> Daftar Sekarang</a>
        <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Masuk</a>
        <a href="#unit-terbuka" class="btn btn-outline-secondary btn-lg"><i class="bi bi-building"></i> Lihat Unit &amp; Gelombang</a>
    </div>
</div>

{{-- Keunggulan --}}
<div class="row g-3 mb-5">
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <i class="bi bi-phone fs-1 text-primary"></i>
                <h6 class="fw-bold mt-3">Daftar dari HP</h6>
                <p class="text-muted small mb-0">Seluruh proses — daftar, bayar, isi formulir, unggah berkas — dapat dilakukan dari ponsel, kapan saja selama gelombang dibuka.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <i class="bi bi-people fs-1 text-primary"></i>
                <h6 class="fw-bold mt-3">Satu Akun, Semua Anak</h6>
                <p class="text-muted small mb-0">Satu akun wali dapat mendaftarkan banyak anak, termasuk ke unit yang berbeda-beda di yayasan yang sama.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <i class="bi bi-wallet2 fs-1 text-primary"></i>
                <h6 class="fw-bold mt-3">Pembayaran Fleksibel</h6>
                <p class="text-muted small mb-0">Bayar melalui transfer manual atau kanal online; tagihan daftar ulang dapat dicicil, dan tersedia pengajuan keringanan bagi yang membutuhkan.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <i class="bi bi-eye fs-1 text-primary"></i>
                <h6 class="fw-bold mt-3">Proses Transparan</h6>
                <p class="text-muted small mb-0">Pantau status pendaftaran, jadwal penting, dan pemberitahuan keputusan langsung dari akun wali Anda.</p>
            </div>
        </div>
    </div>
</div>

{{-- Galeri slide (hanya bila ada foto aktif) --}}
@if($photos->isNotEmpty())
    <div id="galeriSlide" class="carousel slide mb-5 shadow-sm rounded overflow-hidden" data-bs-ride="carousel">
        <div class="carousel-indicators">
            @foreach($photos as $photo)
                <button type="button" data-bs-target="#galeriSlide" data-bs-slide-to="{{ $loop->index }}" class="{{ $loop->first ? 'active' : '' }}" @if($loop->first) aria-current="true" @endif aria-label="Slide {{ $loop->iteration }}"></button>
            @endforeach
        </div>
        <div class="carousel-inner">
            @foreach($photos as $photo)
                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                    <img src="{{ $photo->url() }}" class="d-block w-100" alt="{{ $photo->caption ?? 'Foto kegiatan' }}" style="height: 420px; object-fit: cover;">
                    @if($photo->caption)
                        <div class="carousel-caption d-none d-md-block">
                            <p class="bg-dark bg-opacity-50 rounded px-3 py-2 d-inline-block mb-0">{{ $photo->caption }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#galeriSlide" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Sebelumnya</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#galeriSlide" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Berikutnya</span>
        </button>
    </div>
@endif

{{-- Unit & gelombang yang dibuka --}}
<div id="unit-terbuka" class="mb-5">
    <div class="text-center mb-4">
        <h3 class="fw-bold">Unit &amp; Gelombang yang Sedang Dibuka</h3>
        <p class="text-muted mb-0">Pilih unit tujuan, lalu buat akun atau masuk untuk mulai mendaftar.</p>
    </div>

    @if($units->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-megaphone fs-1 text-muted"></i>
                <p class="text-muted mt-3 mb-0">Saat ini belum ada unit yang membuka pendaftaran. Silakan periksa kembali nanti.</p>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($units as $unit)
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-1">{{ $unit->name }}</h5>
                            <p class="text-muted small mb-2">{{ $unit->level }} &middot; {{ $unit->program?->name }} &middot; Kode {{ $unit->code }}</p>
                            @if($unit->address)
                                <p class="small mb-3">
                                    <i class="bi bi-geo-alt text-primary"></i>
                                    @if($unit->mapsUrl())
                                        <a href="{{ $unit->mapsUrl() }}" target="_blank" rel="noopener" class="text-decoration-none">{{ $unit->address }}</a>
                                    @else
                                        {{ $unit->address }}
                                    @endif
                                </p>
                            @endif

                            @foreach($unit->getAttribute('open_waves') as $wave)
                                <div class="d-flex justify-content-between align-items-center border rounded-3 p-3 mb-2">
                                    <div>
                                        <div class="fw-semibold">{{ $wave->name }}</div>
                                        <div class="text-muted small">{{ $wave->academicYear?->name }} &middot; {{ $wave->opens_at?->format('d M Y') }} – {{ $wave->closes_at?->format('d M Y') }}</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success d-block mb-1">Dibuka</span>
                                        @if($wave->closes_at)
                                            @php($daysLeft = (int) now()->startOfDay()->diffInDays($wave->closes_at->copy()->startOfDay(), false))
                                            <span class="badge bg-warning text-dark">
                                                @if($daysLeft <= 0)
                                                    Ditutup hari ini
                                                @elseif($daysLeft === 1)
                                                    Ditutup besok
                                                @else
                                                    Ditutup {{ $daysLeft }} hari lagi
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <p class="small mb-3">
                                Biaya formulir:
                                @if($unit->form_fee > 0)
                                    <strong>Rp {{ number_format($unit->form_fee, 0, ',', '.') }}</strong>
                                @else
                                    <strong class="text-success">Gratis</strong>
                                @endif
                            </p>

                            <div class="d-flex gap-2 flex-wrap">
                                @auth
                                    @if(auth()->user()->hasRole(\App\Support\SystemAccess::WALI_ROLE))
                                        <a href="{{ route('guardian.applications.create', ['unit' => $unit->id]) }}" class="btn btn-primary">Daftar di Unit Ini</a>
                                    @endif
                                @else
                                    <a href="{{ route('guardian.register') }}" class="btn btn-primary">Daftar Akun untuk Mendaftar</a>
                                @endauth
                                @if($unit->whatsappUrl())
                                    <a href="{{ $unit->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="bi bi-whatsapp"></i> Tanya via WhatsApp</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Video profil (bila diatur) --}}
@if($videoEmbed)
    <div class="mb-5">
        <div class="text-center mb-4">
            <h3 class="fw-bold">Video Profil</h3>
        </div>
        <div class="ratio ratio-16x9 shadow-sm rounded overflow-hidden">
            <iframe src="{{ $videoEmbed }}" title="Video profil yayasan" allowfullscreen loading="lazy"></iframe>
        </div>
    </div>
@endif

{{-- Alur pendaftaran --}}
<div class="mb-5">
    <div class="text-center mb-4">
        <h3 class="fw-bold">Alur Pendaftaran</h3>
        <p class="text-muted mb-0">Enam tahap berurutan — tahap berikutnya terbuka setelah tahap sebelumnya selesai.</p>
    </div>
    <div class="row g-3">
        @foreach([
            ['bi-person-plus', 'Daftar Akun', 'Buat akun wali dengan nomor HP yang aktif.'],
            ['bi-cash-coin', 'Bayar Formulir', 'Selesaikan biaya formulir unit tujuan (ada unit yang gratis).'],
            ['bi-ui-checks', 'Isi Formulir', 'Lengkapi biodata anak, identitas orang tua, dan formulir unit.'],
            ['bi-folder2-open', 'Input Berkas', 'Unggah berkas persyaratan sesuai unit tujuan, lalu kirim.'],
            ['bi-patch-check', 'Verifikasi & Pengumuman', 'Panitia memeriksa berkas; hasil diumumkan sesuai jadwal gelombang.'],
            ['bi-mortarboard', 'Daftar Ulang', 'Bila lulus, selesaikan tagihan daftar ulang — dapat dicicil.'],
        ] as $step)
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">{{ $loop->iteration }}</span>
                            <i class="bi {{ $step[0] }} fs-4 text-primary"></i>
                            <h6 class="fw-bold mb-0">{{ $step[1] }}</h6>
                        </div>
                        <p class="text-muted small mb-0">{{ $step[2] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- FAQ --}}
<div class="mb-5">
    <div class="text-center mb-4">
        <h3 class="fw-bold">Pertanyaan yang Sering Diajukan</h3>
    </div>
    <div class="accordion shadow-sm" id="faqAccordion">
        @foreach([
            ['Berapa biaya formulirnya?', 'Besarnya ditentukan masing-masing unit dan ditampilkan pada kartu unit di halaman ini serta saat Anda mulai mendaftar. Ada unit yang tidak memungut biaya formulir (gratis). Biaya formulir dibayar satu kali sebelum mengisi formulir.'],
            ['Bagaimana cara membayar?', 'Setelah membuat akun dan memulai pendaftaran, Anda dapat membayar melalui transfer manual (ikuti instruksi yang tampil, lalu nyatakan sudah bayar dan unggah bukti transfer) atau kanal online yang tersedia. Panitia akan mengonfirmasi pembayaran manual Anda.'],
            ['Berkas apa saja yang harus disiapkan?', 'Syarat berkas berbeda tiap unit (misalnya kartu keluarga dan akta kelahiran). Daftar lengkapnya tampil pada tahap Input Berkas setelah formulir diisi, dan berkas diunggah dalam bentuk foto/scan dari HP.'],
            ['Apakah satu akun bisa untuk mendaftarkan anak yang lain?', 'Bisa. Satu akun wali dapat mendaftarkan banyak anak, termasuk ke unit yang berbeda. Hubungan Anda dengan tiap anak (Ayah/Ibu/Wali) dipilih pada setiap pendaftaran.'],
            ['Apakah biaya daftar ulang bisa dicicil atau diringankan?', 'Bisa. Dari halaman daftar ulang di akun Anda, ajukan cicilan atau keringanan beserta alasannya; panitia akan memutuskan dan merincikan termin cicilannya bila disetujui.'],
            ['Kapan hasil seleksi diumumkan?', 'Setiap gelombang memiliki jadwal pengumumannya sendiri (dapat berbeda per jalur). Hasil hanya tampil di akun wali Anda setelah waktu pengumuman tiba, dan Anda menerima pemberitahuan di dalam aplikasi.'],
        ] as $faq)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq-{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                        {{ $faq[0] }}
                    </button>
                </h2>
                <div id="faq-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-muted">{{ $faq[1] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- CTA penutup --}}
<div class="card border-0 shadow-sm bg-primary text-white mb-4">
    <div class="card-body text-center py-5">
        <h3 class="fw-bold">Siap Mendaftar?</h3>
        <p class="mb-4">Buat akun wali sekarang dan mulai pendaftaran anak Anda pada gelombang yang sedang dibuka.</p>
        <a href="{{ route('guardian.register') }}" class="btn btn-light btn-lg fw-semibold"><i class="bi bi-pencil-square"></i> Daftar Sekarang</a>
    </div>
</div>
@endsection
