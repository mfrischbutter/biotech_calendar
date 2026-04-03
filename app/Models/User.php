<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'role', 'company_id'])]
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

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function assignedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'employee_id');
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
