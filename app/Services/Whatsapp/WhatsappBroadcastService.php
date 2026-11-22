<?php

namespace App\Services\Whatsapp;

use App\Jobs\SendWhatsappBroadcastStep;
use App\Models\Application;
use App\Models\User;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsappMessageLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin WhatsApp broadcasts to many guardians at once.
 *
 * Combines the two proven legacy designs: durable per-recipient
 * tracking with pause/resume/cancel (Al-Hikmah) and a random
 * delay range plus payment-status targeting (psb-laravel).
 * Sending walks the recipient list one message per chained
 * job, always through WhatsappSender, so the unit → foundation
 * device chain and the message log apply unchanged.
 */
class WhatsappBroadcastService
{
    /** Payment filters an admin can target, keyed for storage. */
    public const PAYMENT_FILTERS = [
        'formulir_belum_lunas' => 'Formulir belum lunas',
        'formulir_lunas' => 'Formulir sudah lunas',
        'tagihan_belum_lunas' => 'Daftar ulang belum lunas',
        'tagihan_lunas' => 'Daftar ulang sudah lunas',
    ];

    /** Message variables replaced per recipient at send time. */
    public const VARIABLES = [
        'nama_wali' => 'Nama wali',
        'nama_anak' => 'Nama anak',
        'unit' => 'Nama unit sekolah',
        'gelombang' => 'Nama gelombang',
    ];

    public function __construct(private WhatsappSender $sender) {}

    /**
     * Applications matching the target filters, restricted to
     * the caller's accessible units when given. Guardians
     * without a phone number cannot receive anything and are
     * left out.
     *
     * @param  array{school_unit_id: int, admission_wave_id?: ?int, application_status?: ?string, payment_filter?: ?string}  $filters
     * @param  ?array<int, int>  $accessibleUnitIds  null = all units
     * @return Collection<int, Application>
     */
    public function resolveApplications(array $filters, ?array $accessibleUnitIds = null): Collection
    {
        $query = Application::query()
            ->with(['user', 'schoolUnit', 'admissionWave'])
            ->where('school_unit_id', $filters['school_unit_id'])
            ->when($accessibleUnitIds !== null, fn ($q) => $q->whereIn('school_unit_id', $accessibleUnitIds))
            ->when(! empty($filters['admission_wave_id']), fn ($q) => $q->where('admission_wave_id', $filters['admission_wave_id']))
            ->when(! empty($filters['application_status']), fn ($q) => $q->where('status', $filters['application_status']));

        match ($filters['payment_filter'] ?? null) {
            'formulir_belum_lunas' => $query->whereNotIn('form_fee_status', ['free', 'paid', 'waived']),
            'formulir_lunas' => $query->whereIn('form_fee_status', ['free', 'paid', 'waived']),
            'tagihan_belum_lunas' => $query->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'paid')),
            'tagihan_lunas' => $query->whereHas('invoice', fn ($q) => $q->where('status', 'paid')),
            default => null,
        };

        return $query->orderBy('id')->get()
            ->filter(fn (Application $application) => filled($application->user?->phone))
            ->values();
    }

    /**
     * Render one recipient's message: spintax groups
     * ({opsi A|opsi B}) resolve to a random option first, then
     * the {variabel} placeholders are replaced.
     */
    public function renderMessage(string $template, Application $application): string
    {
        $message = $template;

        while (preg_match('/\{([^{}]*\|[^{}]*)\}/', $message, $matches)) {
            $options = explode('|', $matches[1]);
            $message = str_replace($matches[0], $options[array_rand($options)], $message);
        }

        return strtr($message, [
            '{nama_wali}' => $application->user?->name ?? '',
            '{nama_anak}' => $application->child_name,
            '{unit}' => $application->schoolUnit?->name ?? '',
            '{gelombang}' => $application->admissionWave?->name ?? '',
        ]);
    }

    /**
     * Snapshot the recipient list into a pending broadcast.
     * Manually picked application ids narrow the filtered set,
     * never widen it, so a tampered form cannot reach children
     * outside the chosen filters or the caller's units.
     *
     * @param  array{school_unit_id: int, admission_wave_id?: ?int, application_status?: ?string, payment_filter?: ?string, message: string, delay_min: int, delay_max: int, application_ids?: ?array<int, int>}  $data
     * @param  ?array<int, int>  $accessibleUnitIds  null = all units
     */
    public function createBroadcast(array $data, User $creator, ?array $accessibleUnitIds = null): WhatsappBroadcast
    {
        $applications = $this->resolveApplications($data, $accessibleUnitIds);

        if (! empty($data['application_ids'])) {
            $picked = array_map('intval', $data['application_ids']);
            $applications = $applications->whereIn('id', $picked)->values();
        }

        return DB::transaction(function () use ($data, $creator, $applications) {
            $broadcast = WhatsappBroadcast::create([
                'school_unit_id' => $data['school_unit_id'],
                'admission_wave_id' => $data['admission_wave_id'] ?? null,
                'application_status' => $data['application_status'] ?? null,
                'payment_filter' => $data['payment_filter'] ?? null,
                'message' => $data['message'],
                'delay_min' => $data['delay_min'],
                'delay_max' => max($data['delay_min'], $data['delay_max']),
                'status' => WhatsappBroadcast::STATUS_PENDING,
                'total_recipients' => $applications->count(),
                'created_by' => $creator->id,
            ]);

            foreach ($applications as $application) {
                WhatsappBroadcastRecipient::create([
                    'whatsapp_broadcast_id' => $broadcast->id,
                    'application_id' => $application->id,
                    'user_id' => $application->user_id,
                    'phone' => $application->user->phone,
                    'child_name' => $application->child_name,
                    'status' => WhatsappBroadcastRecipient::STATUS_PENDING,
                ]);
            }

            return $broadcast;
        });
    }

    /** Start (or restart) the sending chain for a broadcast. */
    public function start(WhatsappBroadcast $broadcast): void
    {
        if (! in_array($broadcast->status, [WhatsappBroadcast::STATUS_PENDING, WhatsappBroadcast::STATUS_PAUSED], true)) {
            return;
        }

        $broadcast->update([
            'status' => WhatsappBroadcast::STATUS_SENDING,
            'started_at' => $broadcast->started_at ?? now(),
            'finished_at' => null,
        ]);

        SendWhatsappBroadcastStep::dispatch($broadcast->id);
    }

    public function pause(WhatsappBroadcast $broadcast): void
    {
        if ($broadcast->status === WhatsappBroadcast::STATUS_SENDING) {
            $broadcast->update(['status' => WhatsappBroadcast::STATUS_PAUSED]);
        }
    }

    public function cancel(WhatsappBroadcast $broadcast): void
    {
        if (in_array($broadcast->status, [WhatsappBroadcast::STATUS_PENDING, WhatsappBroadcast::STATUS_SENDING, WhatsappBroadcast::STATUS_PAUSED], true)) {
            $broadcast->update([
                'status' => WhatsappBroadcast::STATUS_CANCELLED,
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * Send to the next pending recipient, then chain the
     * following step after a random delay inside the configured
     * range. Returns false when the chain stops: the broadcast
     * is no longer sending, or nobody is left (marked
     * completed). One failed number never stops the chain.
     */
    public function processNext(WhatsappBroadcast $broadcast): bool
    {
        if ($broadcast->status !== WhatsappBroadcast::STATUS_SENDING) {
            return false;
        }

        $recipient = $broadcast->recipients()
            ->where('status', WhatsappBroadcastRecipient::STATUS_PENDING)
            ->orderBy('id')
            ->first();

        if ($recipient === null) {
            $broadcast->update([
                'status' => WhatsappBroadcast::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);

            return false;
        }

        $application = $recipient->application;
        $message = $application !== null
            ? $this->renderMessage($broadcast->message, $application)
            : $broadcast->message;

        $log = $this->sender->send(
            $broadcast->school_unit_id,
            $recipient->phone,
            $message,
            WhatsappMessageLog::TYPE_MANUAL,
            $application?->user?->name,
        );

        if ($log->status === WhatsappMessageLog::STATUS_SENT) {
            $recipient->update([
                'status' => WhatsappBroadcastRecipient::STATUS_SENT,
                'sent_at' => now(),
            ]);
            $broadcast->increment('sent_count');
        } else {
            $recipient->update([
                'status' => WhatsappBroadcastRecipient::STATUS_FAILED,
                'error' => 'Status pengiriman: '.$log->status.($log->error ? ' — '.$log->error : ''),
            ]);
            $broadcast->increment('failed_count');
        }

        $broadcast->refresh();

        if ($broadcast->status === WhatsappBroadcast::STATUS_SENDING
            && $broadcast->recipients()->where('status', WhatsappBroadcastRecipient::STATUS_PENDING)->exists()) {
            $delay = random_int($broadcast->delay_min, $broadcast->delay_max);
            SendWhatsappBroadcastStep::dispatch($broadcast->id)->delay($delay);
        } elseif ($broadcast->status === WhatsappBroadcast::STATUS_SENDING) {
            $broadcast->update([
                'status' => WhatsappBroadcast::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);
        }

        return true;
    }
}
