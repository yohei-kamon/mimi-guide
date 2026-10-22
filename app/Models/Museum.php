<?php

namespace App\Models;

use Database\Factories\MuseumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property list<string> $locales
 * @property string $default_locale
 * @property int $guide_price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'locales', 'default_locale', 'guide_price', 'is_active'])]
class Museum extends Model
{
    /** @use HasFactory<MuseumFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locales' => 'array',
            'guide_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the museum's names in each locale.
     *
     * @return HasMany<MuseumTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MuseumTranslation::class);
    }

    /**
     * Get the staff members who belong to the museum.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the museum's exhibitions.
     *
     * @return HasMany<Exhibition, $this>
     */
    public function exhibitions(): HasMany
    {
        return $this->hasMany(Exhibition::class);
    }

    /**
     * Get the translation for the given locale, falling back to the museum's default locale.
     */
    public function translation(?string $locale = null): ?MuseumTranslation
    {
        return $this->translations->firstWhere('locale', $locale ?? $this->default_locale)
            ?? $this->translations->firstWhere('locale', $this->default_locale);
    }

    /**
     * Get the museum's name for the given locale.
     */
    public function name(?string $locale = null): string
    {
        return $this->translation($locale)->name ?? '';
    }

    /**
     * Determine whether visitors can use the guide without buying a pass.
     */
    public function isFree(): bool
    {
        return $this->guide_price === 0;
    }

    /**
     * Scope the query to museums that are open to visitors.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
