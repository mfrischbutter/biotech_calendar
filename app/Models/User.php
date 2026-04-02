<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
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

        return $this->permissions()->where('permission', $permission)->exists();
    }
}
