<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = ['company_id', 'key', 'value'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function get(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        $companyId = $companyId ?? auth()->user()?->company_id;

        if (! $companyId) {
            return $default;
        }

        return cache()->rememberForever("setting.{$companyId}.{$key}", function () use ($key, $default, $companyId) {
            return static::where('company_id', $companyId)
                ->where('key', $key)
                ->value('value') ?? $default;
        });
    }

    public static function set(string $key, mixed $value, ?int $companyId = null): void
    {
        $companyId = $companyId ?? auth()->user()?->company_id;

        if (! $companyId) {
            return;
        }

        static::updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            ['value' => $value],
        );

        cache()->forget("setting.{$companyId}.{$key}");
    }
}
