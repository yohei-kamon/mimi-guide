<?php

namespace App\Livewire\Forms;

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ArtworkTranslationForm extends Form
{
    public string $locale = '';

    public string $title = '';

    public string $artist = '';

    public string $productionYear = '';

    public string $description = '';

    /**
     * Get the locales the artwork can be translated into: the museum's locales except the default one.
     *
     * @return list<string>
     */
    public static function localesFor(Artwork $artwork): array
    {
        $museum = $artwork->exhibition->museum;

        return array_values(array_diff($museum->locales, [$museum->default_locale]));
    }

    /**
     * Select the locale and fill the form from its translation, or leave it empty when there is none.
     */
    public function fillFor(Artwork $artwork, string $locale): void
    {
        $translation = $artwork->translations()->where('locale', $locale)->first();

        $this->locale = $locale;
        $this->title = $translation->title ?? '';
        $this->artist = $translation->artist ?? '';
        $this->productionYear = $translation->production_year ?? '';
        $this->description = $translation->description ?? '';

        $this->resetValidation();
    }

    /**
     * Validate the form and add or replace the artwork's translation in the selected locale.
     */
    public function save(Artwork $artwork): ArtworkTranslation
    {
        $this->validate($this->rulesFor($artwork), attributes: $this->fieldNames());

        return $artwork->translations()->updateOrCreate(
            ['locale' => $this->locale],
            [
                'title' => $this->title,
                'artist' => $this->artist ?: null,
                'production_year' => $this->productionYear ?: null,
                'description' => $this->description ?: null,
            ],
        );
    }

    /**
     * Validate the locale and delete the artwork's translation in it, then clear the form.
     */
    public function delete(Artwork $artwork): void
    {
        $this->validate(['locale' => $this->rulesFor($artwork)['locale']], attributes: $this->fieldNames());

        $artwork->translations()->where('locale', $this->locale)->delete();

        $this->fillFor($artwork, $this->locale);
    }

    /**
     * Get the validation rules; the default locale is edited with the artwork itself.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    private function rulesFor(Artwork $artwork): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(self::localesFor($artwork))],
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'productionYear' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Get the display names of the fields for error messages.
     *
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return [
            'locale' => '言語',
            'title' => '作品名',
            'artist' => '作者',
            'productionYear' => '制作年',
            'description' => '解説文',
        ];
    }
}
