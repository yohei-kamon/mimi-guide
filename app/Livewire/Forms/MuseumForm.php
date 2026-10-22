<?php

namespace App\Livewire\Forms;

use App\Models\Museum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

class MuseumForm extends Form
{
    public string $name = '';

    public string $slug = '';

    /** @var list<string> */
    public array $locales = ['ja'];

    public string $defaultLocale = 'ja';

    /**
     * The museum being edited, so that its own slug is not reported as taken.
     */
    protected ?Museum $editingMuseum = null;

    /**
     * Get the validation rules for the museum form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Museum::class, 'slug')->ignore($this->editingMuseum),
            ],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['distinct', Rule::in(array_keys(config('mimi.locales')))],
            'defaultLocale' => ['required', Rule::in($this->locales)],
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
            'name' => '館名',
            'slug' => '公開用 slug',
            'locales' => '対応言語',
            'locales.*' => '対応言語',
            'defaultLocale' => '既定言語',
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'slug.regex' => '公開用 slug は英小文字・数字・ハイフンで入力してください（先頭と末尾にハイフンは使えません）。',
            'defaultLocale.in' => '既定言語は対応言語の中から選んでください。',
        ];
    }

    /**
     * Fill the form from an existing museum.
     */
    public function fillFromMuseum(Museum $museum): void
    {
        $this->name = $museum->name();
        $this->slug = $museum->slug;
        $this->locales = $museum->locales;
        $this->defaultLocale = $museum->default_locale;
    }

    /**
     * Validate the form and register the museum with its default-locale name.
     */
    public function store(): Museum
    {
        $this->validate();

        return DB::transaction(function () {
            $museum = Museum::create($this->museumAttributes());

            $museum->translations()->create([
                'locale' => $museum->default_locale,
                'name' => $this->name,
            ]);

            return $museum;
        });
    }

    /**
     * Validate the form and update the museum and its default-locale name.
     */
    public function update(Museum $museum): void
    {
        $this->editingMuseum = $museum;

        $this->validate();

        DB::transaction(function () use ($museum) {
            $museum->update($this->museumAttributes());

            $museum->translations()->updateOrCreate(
                ['locale' => $museum->default_locale],
                ['name' => $this->name],
            );
        });
    }

    /**
     * Get the attributes saved on the museum itself, with the locales in the configured order.
     *
     * @return array{slug: string, locales: list<string>, default_locale: string}
     */
    private function museumAttributes(): array
    {
        return [
            'slug' => $this->slug,
            'locales' => array_values(array_intersect(array_keys(config('mimi.locales')), $this->locales)),
            'default_locale' => $this->defaultLocale,
        ];
    }
}
