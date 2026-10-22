<?php

namespace App\Livewire\Forms;

use App\Enums\PublishStatus;
use App\Models\Artwork;
use App\Models\Exhibition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ArtworkForm extends Form
{
    public string $title = '';

    public string $artist = '';

    public string $productionYear = '';

    public string $description = '';

    public string $guideNumber = '';

    public string $sortOrder = '0';

    public string $status = PublishStatus::Draft->value;

    /**
     * Fill the form from an existing artwork.
     */
    public function fillFromArtwork(Artwork $artwork): void
    {
        $translation = $artwork->translation();

        $this->title = $translation->title ?? '';
        $this->artist = $translation->artist ?? '';
        $this->productionYear = $translation->production_year ?? '';
        $this->description = $translation->description ?? '';
        $this->guideNumber = (string) $artwork->guide_number;
        $this->sortOrder = (string) $artwork->sort_order;
        $this->status = $artwork->status->value;
    }

    /**
     * Suggest the next guide number in the exhibition.
     */
    public function suggestGuideNumber(Exhibition $exhibition): void
    {
        $this->guideNumber = (string) ((int) $exhibition->artworks()->max('guide_number') + 1);
    }

    /**
     * Validate the form and add the artwork to the given exhibition.
     */
    public function store(Exhibition $exhibition): Artwork
    {
        $this->validate($this->rulesFor($exhibition), attributes: $this->fieldNames());

        return DB::transaction(function () use ($exhibition) {
            $artwork = $exhibition->artworks()->create($this->artworkAttributes());

            $artwork->translations()->create([
                'locale' => $exhibition->museum->default_locale,
                ...$this->translationAttributes(),
            ]);

            return $artwork;
        });
    }

    /**
     * Validate the form and update the artwork and its default-locale translation.
     */
    public function update(Artwork $artwork): void
    {
        $this->validate($this->rulesFor($artwork->exhibition, $artwork), attributes: $this->fieldNames());

        DB::transaction(function () use ($artwork) {
            $artwork->update($this->artworkAttributes());

            $artwork->translations()->updateOrCreate(
                ['locale' => $artwork->exhibition->museum->default_locale],
                $this->translationAttributes(),
            );
        });
    }

    /**
     * Get the validation rules; the guide number must be unique within the exhibition.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    private function rulesFor(Exhibition $exhibition, ?Artwork $artwork = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'productionYear' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:10000'],
            'guideNumber' => [
                'required',
                'integer',
                'min:1',
                'max:999999',
                Rule::unique(Artwork::class, 'guide_number')
                    ->where('exhibition_id', $exhibition->id)
                    ->ignore($artwork?->id),
            ],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
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
            'title' => '作品名',
            'artist' => '作者',
            'productionYear' => '制作年',
            'description' => '解説文',
            'guideNumber' => 'ガイド番号',
            'sortOrder' => '並び順',
            'status' => '公開状態',
        ];
    }

    /**
     * Get the attributes saved on the artwork itself.
     *
     * @return array{guide_number: int, sort_order: int, status: string}
     */
    private function artworkAttributes(): array
    {
        return [
            'guide_number' => (int) $this->guideNumber,
            'sort_order' => (int) $this->sortOrder,
            'status' => $this->status,
        ];
    }

    /**
     * Get the attributes saved on the translation.
     *
     * @return array{title: string, artist: string|null, production_year: string|null, description: string|null}
     */
    private function translationAttributes(): array
    {
        return [
            'title' => $this->title,
            'artist' => $this->artist ?: null,
            'production_year' => $this->productionYear ?: null,
            'description' => $this->description ?: null,
        ];
    }
}
