<?php

namespace App\Models;

use Database\Factories\MuseumTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $museum_id
 * @property string $locale
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'name'])]
class MuseumTranslation extends Model
{
    /** @use HasFactory<MuseumTranslationFactory> */
    use HasFactory;

    /**
     * Get the museum the translation belongs to.
     *
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }
}
