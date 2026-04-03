<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToCompany;

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
        'user_id',
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

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
