<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\WhatsappOtpService;
use Illuminate\Console\Command;

class CleanupExpiredWhatsappOtps extends Command
{
    protected $signature = 'whatsapp:cleanup-otps';

    protected $description = 'Hapus kode OTP WhatsApp yang sudah kedaluwarsa';

    public function handle(WhatsappOtpService $otp): int
    {
        $deleted = $otp->cleanupExpired();

        $this->info("Kode OTP kedaluwarsa yang dihapus: {$deleted}");

        return self::SUCCESS;
    }
}
