<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'street',
        'zip',
        'city',
        'latitude',
        'longitude',
        'place_id',
        'phone',
        'email',
        'logo_path',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
