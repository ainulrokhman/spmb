<?php

namespace App\Models;

use Database\Factories\ApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    /** @use HasFactory<ApplicationDocumentFactory> */
    use HasFactory;

    /**
     * Per-document verification states (Fase 3).
     *
     * @var array<string, string>
     */
    public const VERIFICATION_STATUSES = [
        'pending' => 'Menunggu',
        'verified' => 'Terverifikasi',
        'revision' => 'Perlu Perbaikan',
    ];

    protected $fillable = [
        'application_id',
        'document_type_id',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'verification_status',
        'verification_note',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verificationLabel(): string
    {
        return self::VERIFICATION_STATUSES[$this->verification_status] ?? $this->verification_status;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
