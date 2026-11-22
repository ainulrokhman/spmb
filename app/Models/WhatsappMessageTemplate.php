<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The editable text of one WhatsApp notification type
 * (WhatsApp Tahap 2, modelled on psb-laravel's message
 * templates). The set of types mirrors the sender's log
 * types, minus the ad-hoc ones (test / manual) that carry
 * their own text. Templates support {variable} placeholders
 * rendered with per-type data.
 *
 * Resolution rule for sending: a stored row that is inactive
 * suppresses the message entirely; a missing row falls back
 * to the built-in default text, so a fresh install sends
 * sensible messages before anyone opens the template page.
 */
class WhatsappMessageTemplate extends Model
{
    /**
     * Templated types: log type => admin label.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        WhatsappMessageLog::TYPE_OTP => 'Kode OTP Pendaftaran',
        WhatsappMessageLog::TYPE_REGISTRATION => 'Pendaftaran Berhasil',
        WhatsappMessageLog::TYPE_PAYMENT => 'Pembayaran',
        WhatsappMessageLog::TYPE_REMINDER => 'Pengingat Tagihan',
        WhatsappMessageLog::TYPE_RESULT => 'Pengumuman Hasil',
        WhatsappMessageLog::TYPE_DECISION => 'Keputusan Keringanan/Cicilan',
    ];

    /**
     * Placeholders each type may use: key => label.
     *
     * @var array<string, array<string, string>>
     */
    public const VARIABLES = [
        WhatsappMessageLog::TYPE_OTP => [
            'nama' => 'Nama wali pendaftar',
            'kode_otp' => 'Kode OTP (6 digit)',
            'berlaku_menit' => 'Masa berlaku kode (menit)',
        ],
        WhatsappMessageLog::TYPE_REGISTRATION => [
            'nama_wali' => 'Nama wali',
            'nama_anak' => 'Nama anak',
            'unit' => 'Nama unit sekolah',
            'gelombang' => 'Nama gelombang',
            'jalur' => 'Jalur pendaftaran',
        ],
        WhatsappMessageLog::TYPE_PAYMENT => [
            'nama_wali' => 'Nama wali',
            'nama_anak' => 'Nama anak',
            'jenis' => 'Jenis pembayaran (Formulir/Daftar Ulang)',
            'nominal' => 'Nominal pembayaran',
            'status' => 'Status pembayaran',
        ],
        WhatsappMessageLog::TYPE_REMINDER => [
            'nama_wali' => 'Nama wali',
            'nama_anak' => 'Nama anak',
            'jenis' => 'Jenis tagihan',
            'nominal' => 'Nominal tagihan',
            'jatuh_tempo' => 'Tanggal jatuh tempo',
        ],
        WhatsappMessageLog::TYPE_RESULT => [
            'nama_wali' => 'Nama wali',
            'nama_anak' => 'Nama anak',
            'unit' => 'Nama unit sekolah',
            'hasil' => 'Hasil seleksi',
            'keterangan' => 'Keterangan tambahan',
        ],
        WhatsappMessageLog::TYPE_DECISION => [
            'nama_wali' => 'Nama wali',
            'nama_anak' => 'Nama anak',
            'jenis_pengajuan' => 'Jenis pengajuan (keringanan/cicilan)',
            'keputusan' => 'Isi keputusan',
            'keterangan' => 'Keterangan tambahan',
        ],
    ];

    /**
     * Built-in default texts, used when no row is stored and
     * restored by the "pulihkan default" action.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        WhatsappMessageLog::TYPE_OTP => "Kode verifikasi pendaftaran SPMB Anda adalah *{kode_otp}*.\n\nKode ini berlaku selama {berlaku_menit} menit. Jangan bagikan kode ini kepada siapa pun.",
        WhatsappMessageLog::TYPE_REGISTRATION => "Assalamu'alaikum, {nama_wali}.\n\nPendaftaran untuk ananda *{nama_anak}* di {unit} ({gelombang} — {jalur}) telah kami terima.\n\nSilakan masuk kembali ke aplikasi SPMB untuk melanjutkan tahap berikutnya: pembayaran formulir, pengisian formulir, dan pengunggahan berkas.",
        WhatsappMessageLog::TYPE_PAYMENT => "Assalamu'alaikum, {nama_wali}.\n\nStatus pembayaran {jenis} untuk ananda *{nama_anak}* sebesar *{nominal}*: *{status}*.\n\nTerima kasih.",
        WhatsappMessageLog::TYPE_REMINDER => "Assalamu'alaikum, {nama_wali}.\n\nMengingatkan: {jenis} untuk ananda *{nama_anak}* sebesar *{nominal}* jatuh tempo pada *{jatuh_tempo}*.\n\nSilakan selesaikan pembayaran melalui aplikasi SPMB. Abaikan pesan ini bila Bapak/Ibu sudah membayar.",
        WhatsappMessageLog::TYPE_RESULT => "Assalamu'alaikum, {nama_wali}.\n\nHasil seleksi untuk ananda *{nama_anak}* di {unit}: *{hasil}*.\n\n{keterangan}",
        WhatsappMessageLog::TYPE_DECISION => "Assalamu'alaikum, {nama_wali}.\n\nPengajuan {jenis_pengajuan} untuk ananda *{nama_anak}* telah diputuskan: *{keputusan}*.\n\n{keterangan}",
    ];

    /**
     * Example values used by the admin preview and test send.
     *
     * @var array<string, string>
     */
    public const SAMPLES = [
        'nama' => 'Ibu Sari',
        'kode_otp' => '123456',
        'berlaku_menit' => '5',
        'nama_wali' => 'Ibu Sari',
        'nama_anak' => 'Ahmad Fauzi',
        'unit' => 'SMA Contoh',
        'gelombang' => 'Gelombang 1',
        'jalur' => 'Reguler',
        'jenis' => 'Formulir',
        'nominal' => 'Rp 250.000',
        'status' => 'Lunas',
        'jatuh_tempo' => '20 Desember 2026',
        'hasil' => 'Diterima',
        'keterangan' => 'Silakan melanjutkan ke tahap daftar ulang.',
        'jenis_pengajuan' => 'keringanan',
        'keputusan' => 'Disetujui sebagian',
    ];

    protected $fillable = [
        'type',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function isTemplatedType(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFor(string $type): array
    {
        return self::VARIABLES[$type] ?? [];
    }

    public static function defaultFor(string $type): string
    {
        return self::DEFAULTS[$type] ?? '';
    }

    /**
     * The template to send with for a type: the stored row when
     * it exists and is active, a transient default instance
     * when no row is stored yet, and null when the stored row
     * is inactive (the type is switched off).
     */
    public static function activeFor(string $type): ?self
    {
        $row = static::query()->where('type', $type)->first();

        if ($row === null) {
            return new static([
                'type' => $type,
                'content' => self::defaultFor($type),
                'is_active' => true,
            ]);
        }

        return $row->is_active ? $row : null;
    }

    /**
     * The stored row for a type, or a transient default
     * instance — for the admin list/edit screens, where an
     * inactive row must still be shown.
     */
    public static function forType(string $type): self
    {
        return static::query()->where('type', $type)->first()
            ?? new static([
                'type' => $type,
                'content' => self::defaultFor($type),
                'is_active' => true,
            ]);
    }

    /**
     * Replace {variable} placeholders with the given data;
     * placeholders without data are left untouched so a typo
     * stays visible instead of silently vanishing.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(array $data): string
    {
        $message = $this->content;

        foreach ($data as $key => $value) {
            $message = str_replace('{'.$key.'}', (string) $value, $message);
        }

        return $message;
    }

    /**
     * The message with sample data, for previews and test
     * sends on the admin page.
     */
    public function preview(): string
    {
        return $this->render(self::SAMPLES);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
