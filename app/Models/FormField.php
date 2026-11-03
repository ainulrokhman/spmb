<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    use HasFactory;

    /**
     * All field types a unit can add to its registration form.
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'select',
        'radio',
        'checkbox',
        'file',
        'school_unit',
        'siblings',
    ];

    /**
     * Indonesian labels for the field types.
     *
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'text' => 'Teks',
        'textarea' => 'Teks Panjang',
        'number' => 'Angka',
        'date' => 'Tanggal',
        'select' => 'Select (dropdown)',
        'radio' => 'Radio',
        'checkbox' => 'Checkbox',
        'file' => 'Unggah File',
        'school_unit' => 'Pilihan Unit Sekolah',
        'siblings' => 'Saudara Kandung (dapat diulang)',
    ];

    /**
     * Answer value meaning "a school outside this foundation"
     * for school_unit fields and sibling rows.
     */
    public const EXTERNAL_SCHOOL = 'external';

    /**
     * Label shown for the external-school answer.
     */
    public const EXTERNAL_SCHOOL_LABEL = 'Sekolah lain di luar yayasan';

    /**
     * Field types that carry a list of selectable options.
     *
     * @var array<int, string>
     */
    public const CHOICE_TYPES = [
        'select',
        'radio',
        'checkbox',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_unit_id',
        'key',
        'label',
        'type',
        'options',
        'is_required',
        'section',
        'sort_order',
        'help_text',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The school unit that owns this field.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    /**
     * Human-readable rendering of a stored answer, resolving
     * the master-data backed types: a school_unit answer
     * becomes the unit's name (or the external-school label),
     * and sibling rows become "Name (School)" entries.
     */
    public function answerDisplay(mixed $value): string
    {
        if ($this->type === 'school_unit') {
            if ($value === self::EXTERNAL_SCHOOL) {
                return self::EXTERNAL_SCHOOL_LABEL;
            }

            if ($value === null || $value === '') {
                return '—';
            }

            return SchoolUnit::find((int) $value)?->name ?? 'Unit tidak ditemukan';
        }

        if ($this->type === 'siblings') {
            if (! is_array($value) || $value === []) {
                return '—';
            }

            return collect($value)->map(function ($row) {
                $school = ! empty($row['unit_id'])
                    ? (SchoolUnit::find((int) $row['unit_id'])?->name ?? 'Unit tidak ditemukan')
                    : ($row['school_name'] ?? self::EXTERNAL_SCHOOL_LABEL);

                return ($row['name'] ?? '').' ('.$school.')';
            })->implode('; ');
        }

        return is_array($value) ? implode(', ', $value) : (string) ($value ?? '—');
    }

    /**
     * Whether this field type needs a list of options.
     */
    public function isChoiceType(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }
}
