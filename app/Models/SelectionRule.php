<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectionRule extends Model
{
    use HasFactory;

    public const KIND_AGE = 'age';

    public const KIND_CHOICE = 'choice';

    public const KIND_NUMERIC = 'numeric';

    public const KIND_ORIGIN = 'origin_unit';

    public const KIND_SIBLINGS = 'siblings';

    public const KIND_CHILD_STATUS = 'child_status';

    public const KIND_MANUAL = 'manual';

    /**
     * Indonesian labels for the rule kinds.
     *
     * @var array<string, string>
     */
    public const KIND_LABELS = [
        self::KIND_AGE => 'Umur Anak',
        self::KIND_CHOICE => 'Jawaban Pilihan Formulir',
        self::KIND_NUMERIC => 'Rentang Angka Formulir',
        self::KIND_ORIGIN => 'Asal Sekolah (Unit Yayasan)',
        self::KIND_SIBLINGS => 'Saudara Kandung',
        self::KIND_CHILD_STATUS => 'Status Anak',
        self::KIND_MANUAL => 'Kriteria Manual (diisi panitia)',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_unit_id',
        'kind',
        'name',
        'config',
        'sort_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The unit this rule belongs to.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    /**
     * Short Indonesian summary of the rule's configuration,
     * for the rules list.
     */
    public function summary(): string
    {
        $config = $this->config ?? [];

        return match ($this->kind) {
            self::KIND_AGE => collect($config['brackets'] ?? [])
                ->map(fn ($b) => ($b['min'] ?? 0).'–'.(($b['max'] ?? null) === null ? '∞' : $b['max']).' th: '.($b['points'] ?? 0))
                ->implode('; '),
            self::KIND_CHOICE => 'Bidang '.($config['field_key'] ?? '?').' — '.count($config['map'] ?? []).' jawaban berpoin',
            self::KIND_NUMERIC => 'Bidang '.($config['field_key'] ?? '?').' — '.count($config['ranges'] ?? []).' rentang',
            self::KIND_ORIGIN => 'Bidang '.($config['field_key'] ?? '?').' — '.($config['points'] ?? 0).' poin bila lulusan unit yayasan',
            self::KIND_SIBLINGS => 'Bidang '.($config['field_key'] ?? '?').' — '.($config['points_internal'] ?? 0).'/saudara di yayasan, '.($config['points_external'] ?? 0).'/saudara di luar'.(($config['cap'] ?? null) !== null ? ', maks '.($config['cap']) : ''),
            self::KIND_CHILD_STATUS => collect($config['map'] ?? [])
                ->map(fn ($points, $statusId) => (ChildStatus::find((int) $statusId)?->name ?? '?').': '.$points)
                ->implode('; '),
            self::KIND_MANUAL => 'Diisi panitia, maks '.($config['max_points'] ?? 0).' poin',
            default => '',
        };
    }

    /**
     * Maximum points for a manual criterion (0 for others).
     */
    public function maxPoints(): int
    {
        return $this->kind === self::KIND_MANUAL ? (int) ($this->config['max_points'] ?? 0) : 0;
    }
}
