{{-- Connection status badge for one WhatsApp device. Expects $device. --}}
@php
    $badge = match ($device->status) {
        \App\Models\WhatsappDevice::STATUS_CONNECTED => 'text-bg-success',
        \App\Models\WhatsappDevice::STATUS_DISCONNECTED => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
@endphp
<span class="badge {{ $badge }}">{{ $device->statusLabel() }}</span>
@if($device->last_checked_at)
    <span class="text-muted small d-block">dicek {{ $device->last_checked_at->format('d M Y H:i') }}</span>
@endif
