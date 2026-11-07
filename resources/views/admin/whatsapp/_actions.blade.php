{{-- Action buttons + test-send form for one WhatsApp device. Expects $device. --}}
<div class="d-flex flex-wrap gap-1">
    <form action="{{ route('admin.whatsapp.devices.refresh', $device) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Periksa status koneksi dari provider">
            <i class="bi bi-arrow-repeat"></i> Status
        </button>
    </form>

    @if($device->provider === \App\Models\WhatsappDevice::PROVIDER_GOWA && ! $device->isConnected())
        <a href="{{ route('admin.whatsapp.devices.login', $device) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-qr-code"></i> Hubungkan
        </a>
    @endif

    <form action="{{ route('admin.whatsapp.devices.reconnect', $device) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sambung ulang ke WhatsApp">
            <i class="bi bi-plug"></i> Sambung Ulang
        </button>
    </form>

    @if($device->provider === \App\Models\WhatsappDevice::PROVIDER_GOWA)
        <form action="{{ route('admin.whatsapp.devices.logout', $device) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-warning" title="Putuskan dari WhatsApp (slot tetap ada, dapat dipasangkan lagi)">
                <i class="bi bi-box-arrow-right"></i> Putuskan
            </button>
        </form>
    @endif

    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#uji-{{ $device->id }}" aria-expanded="false">
        <i class="bi bi-send"></i> Uji Kirim
    </button>

    <form action="{{ route('admin.whatsapp.devices.destroy', $device) }}" method="POST" onsubmit="return confirm('Hapus device {{ $device->label() }}? Pengiriman lewat device ini akan berhenti.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-trash"></i> Hapus
        </button>
    </form>
</div>

<div class="collapse mt-2" id="uji-{{ $device->id }}">
    <form action="{{ route('admin.whatsapp.devices.test', $device) }}" method="POST" class="border rounded-3 p-3 bg-body-tertiary">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary" for="uji-phone-{{ $device->id }}">Nomor Tujuan</label>
                <input type="text" class="form-control form-control-sm" id="uji-phone-{{ $device->id }}" name="phone" placeholder="08xxxxxxxxxx" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold text-secondary" for="uji-message-{{ $device->id }}">Isi Pesan</label>
                <input type="text" class="form-control form-control-sm" id="uji-message-{{ $device->id }}" name="message" placeholder="Pesan uji dari SPMB" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-success w-100">Kirim</button>
            </div>
        </div>
    </form>
</div>
