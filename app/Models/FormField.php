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
    ];

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
     * Whether this field type needs a list of options.
     */
    public function isChoiceType(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }
}
