@extends('layouts.guardian')

@section('title', 'Notifikasi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="fw-bold mb-0">Notifikasi</h3>
    @if(auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('guardian.notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all"></i> Tandai Semua Terbaca</button>
        </form>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <p class="text-muted small">Keputusan panitia, status pembayaran, dan pengingat untuk pendaftaran anak Anda. Klik notifikasi untuk membuka halaman terkait.</p>

        @if($notifications->isEmpty())
            <p class="text-muted mb-0">Belum ada notifikasi.</p>
        @else
            <div class="list-group list-group-flush">
                @foreach($notifications as $notification)
                    <a href="{{ route('guardian.notifications.show', $notification) }}" class="list-group-item list-group-item-action px-2 {{ $notification->read_at === null ? 'bg-light' : '' }}">
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
