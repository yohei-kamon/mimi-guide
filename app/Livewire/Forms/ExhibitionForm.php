<?php

namespace App\Livewire\Forms;

use App\Enums\PublishStatus;
use App\Models\Exhibition;
use App\Models\Museum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ExhibitionForm extends Form
{
    public string $name = '';

    public string $description = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $status = PublishStatus::Draft->value;

    /**
     * Get the validation rules for the exhibition form.
     *
     * The end date is compared with the start date's value, because a field name
     * in `after_or_equal` would not carry the form's `form.` prefix.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => array_filter(['nullable', 'date', $this->startsOn !== '' ? 'after_or_equal:'.$this->startsOn : null]),
            'status' => ['required', Rule::enum(PublishStatus::class)],
        ];
    }

    /**
     * Get the display names of the fields for error messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => '展示名',
            'description' => '説明',
            'startsOn' => '開始日',
            'endsOn' => '終了日',
            'status' => '公開状態',
        ];
    }

    /**
     * Fill the form from an existing exhibition.
     */
    public function fillFromExhibition(Exhibition $exhibition): void
    {
        $translation = $exhibition->translation();

        $this->name = $translation->name ?? '';
        $this->description = $translation->description ?? '';
        $this->startsOn = $exhibition->starts_on?->toDateString() ?? '';
        $this->endsOn = $exhibition->ends_on?->toDateString() ?? '';
        $this->status = $exhibition->status->value;
    }

    /**
     * Validate the form and create the exhibition in the given museum.
     */
    public function store(Museum $museum): Exhibition
    {
        $this->validate();

        return DB::transaction(function () use ($museum) {
            $exhibition = $museum->exhibitions()->create($this->exhibitionAttributes());

            $exhibition->translations()->create([
                'locale' => $museum->default_locale,
                ...$this->translationAttributes(),
            ]);

            return $exhibition;
        });
    }

    /**
     * Validate the form and update the exhibition and its default-locale translation.
     */
    public function update(Exhibition $exhibition): void
    {
        $this->validate();

        DB::transaction(function () use ($exhibition) {
            $exhibition->update($this->exhibitionAttributes());

            $exhibition->translations()->updateOrCreate(
                ['locale' => $exhibition->museum->default_locale],
                $this->translationAttributes(),
            );
        });
    }

    /**
     * Get the attributes saved on the exhibition itself.
     *
     * @return array{status: string, starts_on: string|null, ends_on: string|null}
     */
    private function exhibitionAttributes(): array
    {
        return [
            'status' => $this->status,
            'starts_on' => $this->startsOn ?: null,
            'ends_on' => $this->endsOn ?: null,
        ];
    }

    /**
     * Get the attributes saved on the translation.
     *
     * @return array{name: string, description: string|null}
     */
    private function translationAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description ?: null,
        ];
    }
}
