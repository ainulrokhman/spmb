@extends('layouts.admin')

@section('title', 'Broadcast WhatsApp')
@section('page-title', 'Broadcast WhatsApp')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}">WhatsApp</a></li>
    <li class="breadcrumb-item active" aria-current="page">Broadcast</li>
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-body"><i class="bi bi-megaphone me-2"></i>Broadcast WhatsApp</h5>
            <p class="text-muted small mb-0">Kirim satu pesan ke banyak wali sekaligus, dengan jeda acak antar pesan dan progres per penerima.</p>
        </div>
        <a href="{{ route('admin.whatsapp.broadcasts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Broadcast Baru</a>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Dibuat</th>
                        <th>Unit</th>
                        <th>Target</th>
                        <th>Progres</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($broadcasts as $broadcast)
                        <tr>
                            <td class="text-nowrap">
                                {{ $broadcast->created_at->format('d M Y H:i') }}
                                <div class="text-muted small">oleh {{ $broadcast->creator?->name ?? '-' }}</div>
                            </td>
                            <td>{{ $broadcast->schoolUnit?->name }}</td>
                            <td class="small">
                                {{ $broadcast->admissionWave?->name ?? 'Semua gelombang' }}
                                @if($broadcast->application_status)
                                    <div>{{ \App\Models\Application::STATUSES[$broadcast->application_status] ?? $broadcast->application_status }}</div>
                                @endif
                                @if($broadcast->payment_filter)
                                    <div>{{ \App\Services\Whatsapp\WhatsappBroadcastService::PAYMENT_FILTERS[$broadcast->payment_filter] ?? $broadcast->payment_filter }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="progress" role="progressbar" aria-valuenow="{{ $broadcast->progressPercent() }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ $broadcast->progressPercent() }}%"></div>
                                </div>
                                <div class="text-muted small mt-1">{{ $broadcast->sent_count + $broadcast->failed_count }} / {{ $broadcast->total_recipients }} (terkirim {{ $broadcast->sent_count }}, gagal {{ $broadcast->failed_count }})</div>
                            </td>
                            <td>
                                @php($badge = match ($broadcast->status) {
                                    \App\Models\WhatsappBroadcast::STATUS_SENDING => 'text-bg-primary',
                                    \App\Models\WhatsappBroadcast::STATUS_COMPLETED => 'text-bg-success',
                                    \App\Models\WhatsappBroadcast::STATUS_CANCELLED => 'text-bg-danger',
                                    \App\Models\WhatsappBroadcast::STATUS_PAUSED => 'text-bg-warning',
                                    default => 'text-bg-secondary',
                                })
                                <span class="badge {{ $badge }}">{{ $broadcast->statusLabel() }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.whatsapp.broadcasts.show', $broadcast) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada broadcast. Buat broadcast pertama Anda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $broadcasts->links() }}</div>
    </div>
</div>
@endsection
