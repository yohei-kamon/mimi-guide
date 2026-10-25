<?php

namespace App\Models;

use App\Concerns\HasRandomSlug;
use App\Enums\PublishStatus;
use Database\Factories\ExhibitionFactory;
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
 * @property int $museum_id
 * @property string $slug
 * @property PublishStatus $status
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['status', 'starts_on', 'ends_on'])]
class Exhibition extends Model
{
    /** @use HasFactory<ExhibitionFactory> */
    use HasFactory, HasRandomSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Get the museum that holds the exhibition.
     *
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    /**
     * Get the artworks shown in the exhibition.
     *
     * @return HasMany<Artwork, $this>
     */
    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    /**
     * Get the exhibition's names and descriptions in each locale.
     *
     * @return HasMany<ExhibitionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ExhibitionTranslation::class);
    }

    /**
     * Get the translation for the given locale, falling back to the museum's default locale.
     */
    public function translation(?string $locale = null): ?ExhibitionTranslation
    {
        $defaultLocale = $this->museum->default_locale;

        return $this->translations->firstWhere('locale', $locale ?? $defaultLocale)
            ?? $this->translations->firstWhere('locale', $defaultLocale);
    }

    /**
     * Get one translated attribute for the given locale, falling back to the default locale when it is blank.
     *
     * @param  'name'|'description'  $attribute
     */
    public function translated(string $attribute, ?string $locale = null): ?string
    {
        $value = $this->translation($locale)?->{$attribute};

        return filled($value) ? $value : $this->translation()?->{$attribute};
    }

    /**
     * Get the exhibition's name for the given locale.
     */
    public function name(?string $locale = null): string
    {
        return $this->translation($locale)->name ?? '';
    }

    /**
     * Determine whether the exhibition is published.
     */
    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }

    /**
     * Get the period for display, e.g. "2026年4月1日〜2026年6月30日" or "常設".
     */
    public function periodLabel(): string
    {
        if ($this->starts_on === null && $this->ends_on === null) {
            return '常設';
        }

        return $this->starts_on?->isoFormat('YYYY年M月D日').'〜'.$this->ends_on?->isoFormat('YYYY年M月D日');
    }

    /**
     * Scope the query to exhibitions visitors can see: published and within the period today.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $today = today();

        $query->where('status', PublishStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }
}
