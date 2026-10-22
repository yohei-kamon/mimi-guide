<?php

namespace App\Models;

use App\Concerns\HasRandomSlug;
use App\Enums\PublishStatus;
use Database\Factories\ArtworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exhibition_id
 * @property string $slug
 * @property int $guide_number
 * @property int $sort_order
 * @property string|null $image_path
 * @property PublishStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['guide_number', 'sort_order', 'status'])]
class Artwork extends Model
{
    /** @use HasFactory<ArtworkFactory> */
    use HasFactory, HasRandomSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guide_number' => 'integer',
            'sort_order' => 'integer',
            'status' => PublishStatus::class,
        ];
    }

    /**
     * Get the exhibition the artwork is shown in.
     *
     * @return BelongsTo<Exhibition, $this>
     */
    public function exhibition(): BelongsTo
    {
        return $this->belongsTo(Exhibition::class);
    }

    /**
     * Get the artwork's titles and descriptions in each locale.
     *
     * @return HasMany<ArtworkTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ArtworkTranslation::class);
    }

    /**
     * Get the translation for the given locale, falling back to the museum's default locale.
     */
    public function translation(?string $locale = null): ?ArtworkTranslation
    {
        $defaultLocale = $this->exhibition->museum->default_locale;

        return $this->translations->firstWhere('locale', $locale ?? $defaultLocale)
            ?? $this->translations->firstWhere('locale', $defaultLocale);
    }

    /**
     * Get the artwork's title for the given locale.
     */
    public function title(?string $locale = null): string
    {
        return $this->translation($locale)->title ?? '';
    }

    /**
     * Determine whether the artwork is published.
     */
    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }

    /**
     * Scope the query to published artworks.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PublishStatus::Published);
    }

    /**
     * Scope the query to the playback order: sort order, then guide number.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('guide_number');
    }
}
