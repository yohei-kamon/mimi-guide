<?php

namespace App\Models;

use Database\Factories\ExhibitionTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exhibition_id
 * @property string $locale
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'name', 'description'])]
class ExhibitionTranslation extends Model
{
    /** @use HasFactory<ExhibitionTranslationFactory> */
    use HasFactory;

    /**
     * Get the exhibition the translation belongs to.
     *
     * @return BelongsTo<Exhibition, $this>
     */
    public function exhibition(): BelongsTo
    {
        return $this->belongsTo(Exhibition::class);
    }
}
