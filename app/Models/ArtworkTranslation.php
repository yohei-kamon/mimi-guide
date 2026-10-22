<?php

namespace App\Models;

use Database\Factories\ArtworkTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $artwork_id
 * @property string $locale
 * @property string $title
 * @property string|null $artist
 * @property string|null $production_year
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'title', 'artist', 'production_year', 'description'])]
class ArtworkTranslation extends Model
{
    /** @use HasFactory<ArtworkTranslationFactory> */
    use HasFactory;

    /**
     * Get the artwork the translation belongs to.
     *
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }
}
