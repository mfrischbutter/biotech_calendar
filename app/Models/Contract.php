<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use BelongsToCompany;

    public const KINDS = ['ohne_termin', 'kundentermin'];

    protected $fillable = [
        'company_id',
        'contract_number',
        'title',
        'kind',
        'description',
        'street',
        'zip',
        'city',
        'latitude',
        'longitude',
        'place_id',
    ];

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'contract_client')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
