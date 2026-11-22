@extends('layouts.admin')

@section('title', 'Detail Broadcast')
@section('page-title', 'Detail Broadcast')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.broadcasts.index') }}">Broadcast</a></li>
    <li class="breadcrumb-item active" aria-current="page">Detail</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-3 mb-4 p-3 d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0 fw-bold text-body"><i class="bi bi-megaphone me-2"></i>Broadcast {{ $broadcast->created_at->format('d M Y H:i') }}</h5>
        @php($badge = match ($broadcast->status) {
            \App\Models\WhatsappBroadcast::STATUS_SENDING => 'text-bg-primary',
            \App\Models\WhatsappBroadcast::STATUS_COMPLETED => 'text-bg-success',
            \App\Models\WhatsappBroadcast::STATUS_CANCELLED => 'text-bg-danger',
            \App\Models\WhatsappBroadcast::STATUS_PAUSED => 'text-bg-warning',
            default => 'text-bg-secondary',
        })
        <span class="badge {{ $badge }}" id="status-badge">{{ $broadcast->statusLabel() }}</span>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="text-muted small">Unit</div>
                <div class="fw-semibold">{{ $broadcast->schoolUnit?->name }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Gelombang</div>
                <div class="fw-semibold">{{ $broadcast->admissionWave?->name ?? 'Semua gelombang' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status Pendaftaran</div>
                <div class="fw-semibold">{{ $broadcast->application_status ? (\App\Models\Application::STATUSES[$broadcast->application_status] ?? $broadcast->application_status) : 'Semua status' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status Pembayaran</div>
                <div class="fw-semibold">{{ $broadcast->payment_filter ? (\App\Services\Whatsapp\WhatsappBroadcastService::PAYMENT_FILTERS[$broadcast->payment_filter] ?? $broadcast->payment_filter) : 'Semua' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Jeda Antar Pesan</div>
                <div class="fw-semibold">{{ $broadcast->delay_min }}–{{ $broadcast->delay_max }} detik (acak)</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Dibuat Oleh</div>
                <div class="fw-semibold">{{ $broadcast->creator?->name ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Mulai</div>
                <div class="fw-semibold">{{ $broadcast->started_at?->format('d M Y H:i') ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Selesai</div>
                <div class="fw-semibold">{{ $broadcast->finished_at?->format('d M Y H:i') ?? '-' }}</div>
            </div>
        </div>

        <div class="text-muted small">Isi Pesan</div>
        <div class="border rounded-3 p-3 mb-3" style="white-space: pre-wrap;">{{ $broadcast->message }}</div>

        <div class="d-flex justify-content-between small text-muted">
            <span>Progres: <span id="progress-text">{{ $broadcast->sent_count + $broadcast->failed_count }} / {{ $broadcast->total_recipients }}</span></span>
            <span>Terkirim <span id="sent-count">{{ $broadcast->sent_count }}</span> · Gagal <span id="failed-count">{{ $broadcast->failed_count }}</span></span>
        </div>
        <div class="progress mt-1 mb-3" role="progressbar" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" id="progress-bar" style="width: {{ $broadcast->progressPercent() }}%"></div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if(in_array($broadcast->status, [\App\Models\WhatsappBroadcast::STATUS_PENDING, \App\Models\WhatsappBroadcast::STATUS_PAUSED], true))
                <form action="{{ route('admin.whatsapp.broadcasts.start', $broadcast) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>{{ $broadcast->status === \App\Models\WhatsappBroadcast::STATUS_PAUSED ? 'Lanjutkan Kirim' : 'Mulai Kirim' }}</button>
                </form>
            @endif
            @if($broadcast->status === \App\Models\WhatsappBroadcast::STATUS_SENDING)
                <form action="{{ route('admin.whatsapp.broadcasts.pause', $broadcast) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-warning"><i class="bi bi-pause-fill me-1"></i>Jeda</button>
                </form>
            @endif
            @if(in_array($broadcast->status, [\App\Models\WhatsappBroadcast::STATUS_PENDING, \App\Models\WhatsappBroadcast::STATUS_SENDING, \App\Models\WhatsappBroadcast::STATUS_PAUSED], true))
                <form action="{{ route('admin.whatsapp.broadcasts.cancel', $broadcast) }}" method="POST" onsubmit="return confirm('Batalkan broadcast ini? Penerima yang belum terkirim tidak akan dikirimi pesan.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Batalkan</button>
                </form>
            @endif
            <a href="{{ route('admin.whatsapp.broadcasts.index') }}" class="btn btn-outline-secondary">Kembali</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="mb-0 fw-bold text-body"><i class="bi bi-people me-2"></i>Penerima</h5>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Anak</th>
                        <th>Nomor WhatsApp</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recipients as $recipient)
                        <tr>
                            <td>{{ $recipient->child_name }}</td>
                            <td>{{ $recipient->phone }}</td>
                            <td>
                                @php($recipientBadge = match ($recipient->status) {
                                    \App\Models\WhatsappBroadcastRecipient::STATUS_SENT => 'text-bg-success',
                                    \App\Models\WhatsappBroadcastRecipient::STATUS_FAILED => 'text-bg-danger',
                                    default => 'text-bg-secondary',
                                })
                                <span class="badge {{ $recipientBadge }}">{{ $recipient->statusLabel() }}</span>
                            </td>
                            <td class="small text-muted">
                                @if($recipient->status === \App\Models\WhatsappBroadcastRecipient::STATUS_SENT)
                                    {{ $recipient->sent_at?->format('d M Y H:i') }}
                                @else
                                    {{ $recipient->error ?? '' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Tidak ada penerima pada broadcast ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $recipients->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
@if($broadcast->status === \App\Models\WhatsappBroadcast::STATUS_SENDING)
<script>
    const statusUrl = '{{ route('admin.whatsapp.broadcasts.status', $broadcast) }}';
    const poll = setInterval(async () => {
        try {
            const response = await fetch(statusUrl, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            document.getElementById('progress-bar').style.width = data.percent + '%';
            document.getElementById('progress-text').textContent = (data.sent + data.failed) + ' / ' + data.total;
            document.getElementById('sent-count').textContent = data.sent;
            document.getElementById('failed-count').textContent = data.failed;
            if (data.status !== 'sending') {
                clearInterval(poll);
                location.reload();
            }
        } catch (error) {
            // Halaman tetap menampilkan angka terakhir; polling berikutnya mencoba lagi.
        }
    }, 5000);
</script>
@endif
@endpush
