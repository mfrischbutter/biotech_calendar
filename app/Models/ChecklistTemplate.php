<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable checklist for a service type, applied to an appointment from the
 * appointment form ("Vorlage übernehmen").
 *
 * @property int $id
 * @property string $name
 * @property string|null $kind
 * @property array<int, string> $items
 * @property int $sort_order
 */
class ChecklistTemplate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'kind',
        'items',
        'sort_order',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    /**
     * The template rendered as appointment checklist items — every entry
     * starts unchecked.
     *
     * @return array<int, array{text: string, checked: bool}>
     */
    public function toChecklist(): array
    {
        return array_values(array_map(
            fn (string $text) => ['text' => $text, 'checked' => false],
            $this->items ?? [],
        ));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
