<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\SystemAccess;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * School units this user is assigned to as a committee member.
     */
    public function schoolUnits(): BelongsToMany
    {
        return $this->belongsToMany(SchoolUnit::class, 'user_school_units')->withTimestamps();
    }

    /**
     * Whether the user bypasses unit assignments: foundation-level
     * roles (SuperAdmin and Admin) access every school unit.
     */
    public function hasGlobalUnitAccess(): bool
    {
        return $this->hasRole([SystemAccess::SUPER_ADMIN_ROLE, SystemAccess::ADMIN_ROLE]);
    }

    /**
     * Whether the user may work with the given school unit: global
     * roles always may, everyone else only through their assignments.
     */
    public function hasUnitAccess(SchoolUnit|int $schoolUnit): bool
    {
        if ($this->hasGlobalUnitAccess()) {
            return true;
        }

        $schoolUnitId = $schoolUnit instanceof SchoolUnit ? $schoolUnit->id : $schoolUnit;

        return $this->schoolUnits()->where('school_units.id', $schoolUnitId)->exists();
    }

    /**
     * Ids of the school units the user may work with.
     *
     * @return list<int>
     */
    public function accessibleUnitIds(): array
    {
        if ($this->hasGlobalUnitAccess()) {
            return SchoolUnit::query()->pluck('id')->all();
        }

        return $this->schoolUnits()->pluck('school_units.id')->all();
    }

    /**
     * Define the activity log options for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
