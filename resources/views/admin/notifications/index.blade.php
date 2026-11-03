@extends('layouts.admin')

@section('title', 'Notifikasi')
@section('page-title', 'Notifikasi')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Notifikasi</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">Notifikasi</h5>
            <p class="text-muted small mb-0">Pengajuan dan pembayaran yang menunggu keputusan Anda. Klik notifikasi untuk membuka halaman tindak lanjutnya.</p>
        </div>
        @if(auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all"></i> Tandai Semua Terbaca</button>
            </form>
        @endif
    </div>

    <div class="card-body px-4 pb-4">
        @if($notifications->isEmpty())
            <p class="text-muted mb-0">Belum ada notifikasi.</p>
        @else
            <div class="list-group list-group-flush">
                @foreach($notifications as $notification)
                    <a href="{{ route('admin.notifications.show', $notification) }}" class="list-group-item list-group-item-action px-2 {{ $notification->read_at === null ? 'bg-light' : '' }}">
                        <div class="d-flex align-items-start gap-3">
                            <i class="{{ $notification->data['icon'] ?? 'bi bi-bell' }} fs-5 text-primary mt-1"></i>
                            <div class="flex-grow-1">
                                <div class="{{ $notification->read_at === null ? 'fw-bold' : 'fw-semibold' }} text-body">
                                    {{ $notification->data['title'] ?? 'Notifikasi' }}
                                    @if($notification->read_at === null)
                                        <span class="badge bg-danger ms-1">Baru</span>
                                    @endif
                                </div>
                                <div class="text-muted small">{{ $notification->data['message'] ?? '' }}</div>
                            </div>
                            <span class="text-muted small text-nowrap">{{ $notification->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-3">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
