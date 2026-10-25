<?php

namespace App\Models;

use Database\Factories\MuseumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Cookie;

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
     * The session key that holds the locale the visitor chose.
     */
    public const VISITOR_LOCALE_SESSION_KEY = 'visitor_locale';

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
     * Get the guide passes sold for the museum.
     *
     * @return HasMany<GuidePass, $this>
     */
    public function guidePasses(): HasMany
    {
        return $this->hasMany(GuidePass::class);
    }

    /**
     * Get the name of the cookie that holds the visitor's pass token for this museum.
     *
     * @return non-empty-string
     */
    public function passCookieName(): string
    {
        return "guide_pass_{$this->id}";
    }

    /**
     * Get the pass token for this museum from the visitor's cookie.
     */
    public function passTokenFrom(Request $request): ?string
    {
        $token = $request->cookie($this->passCookieName());

        return is_string($token) ? $token : null;
    }

    /**
     * Make the cookie that keeps the pass in the visitor's browser, outliving the pass itself.
     */
    public function passCookie(GuidePass $guidePass): Cookie
    {
        return cookie($this->passCookieName(), $guidePass->token, 60 * GuidePass::LIFETIME_HOURS * 2);
    }

    /**
     * Get the museum's paid, unexpired pass with the given token.
     */
    public function validPass(?string $token): ?GuidePass
    {
        if (blank($token)) {
            return null;
        }

        return $this->guidePasses()->valid()->where('token', $token)->first();
    }

    /**
     * Determine whether a visitor holding the given pass token can read the descriptions and hear the audio.
     */
    public function grantsAccessWith(?string $token): bool
    {
        return $this->isFree() || $this->validPass($token) !== null;
    }

    /**
     * Get the visitor's locale for this museum.
     *
     * A supported `?lang=` is saved in the session. The session's locale is used when the museum supports it,
     * otherwise the museum's default locale.
     */
    public function visitorLocale(Request $request): string
    {
        $requestedLocale = $request->query('lang');

        if (is_string($requestedLocale) && in_array($requestedLocale, $this->locales, true)) {
            session()->put(self::VISITOR_LOCALE_SESSION_KEY, $requestedLocale);
        }

        $sessionLocale = session(self::VISITOR_LOCALE_SESSION_KEY);

        return is_string($sessionLocale) && in_array($sessionLocale, $this->locales, true)
            ? $sessionLocale
            : $this->default_locale;
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
