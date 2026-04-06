<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'color',
        'sort_order',
    ];

    public const COLORS = [
        '#3b82f6', // blue
        '#f97316', // orange
        '#22c55e', // green
        '#a855f7', // purple
        '#ec4899', // pink
        '#6b7280', // gray
        '#ef4444', // red
        '#14b8a6', // teal
        '#f59e0b', // amber
        '#8b5cf6', // violet
        '#06b6d4', // cyan
        '#84cc16', // lime
        '#e11d48', // rose
        '#0ea5e9', // sky
        '#d946ef', // fuchsia
        '#78716c', // stone
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
