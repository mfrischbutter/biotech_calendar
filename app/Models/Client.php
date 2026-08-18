<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Client extends Model
{
    use BelongsToCompany, HasFactory;

    public const SALUTATIONS = ['Herr', 'Frau'];

    protected $fillable = [
        'company_id',
        'salutation',
        'first_name',
        'last_name',
        'company_name',
        'billing_name',
        'street',
        'zip',
        'city',
        'phone',
        'email',
        'notes',
        'access_notes',
        'user_id',
        'latitude',
        'longitude',
        'place_id',
    ];

    protected $appends = ['name'];

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_client')->withTimestamps();
    }
}
