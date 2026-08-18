<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'email', 'locale', 'password', 'role', 'staff_role_id', 'company_id', 'dashboard_comments_read_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['name'];

    public const ROLE_OWNER = 'owner';

    public const ROLE_EMPLOYEE = 'employee';

    public const ROLES = [self::ROLE_OWNER, self::ROLE_EMPLOYEE];

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dashboard_comments_read_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Ordered by key so the set is deterministic regardless of storage engine —
     * insertion order differs between MySQL and SQLite and would otherwise leak
     * into golden files.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class)->orderBy('permission');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function assignedAppointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_user')->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        return $this->permissions->contains('permission', $permission);
    }

    public function syncPermissions(array $permissions): void
    {
        $this->permissions()->delete();

        if (! empty($permissions)) {
            $this->permissions()->createMany(
                array_map(fn (string $p) => ['permission' => $p, 'company_id' => $this->company_id], $permissions)
            );
        }

        $this->unsetRelation('permissions');
    }

    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Apply a role's permission preset to this user.
     * The role is remembered so the UI can show which preset is in effect.
     */
    public function applyStaffRole(StaffRole $role): void
    {
        $this->staff_role_id = $role->id;
        $this->save();
        $this->syncPermissions($role->permissions ?? []);
    }

    /** @return array<int, string> */
    public function permissionKeys(): array
    {
        return $this->permissions->pluck('permission')->all();
    }

    /**
     * True when this user's permissions have been hand-tuned away from their
     * role preset — surfaced in the UI as an "angepasst" badge.
     */
    public function hasCustomPermissions(): bool
    {
        if (! $this->staffRole) {
            return false;
        }

        return ! $this->staffRole->matches($this->permissionKeys());
    }

    public function getOwner(): self
    {
        if ($this->isOwner()) {
            return $this;
        }

        return self::where('role', self::ROLE_OWNER)
            ->where('company_id', $this->company_id)
            ->firstOrFail();
    }
}
