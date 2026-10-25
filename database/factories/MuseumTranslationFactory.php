<?php

namespace Database\Factories;

use App\Models\MuseumTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MuseumTranslation>
 */
class MuseumTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The museum is not created here because MuseumFactory already adds the
     * default-locale translation. Use `->for($museum)` to attach one, and
     * pick a non-default locale with `locale()` to avoid the unique constraint.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'ja',
            'name' => fake()->city().'美術館',
        ];
    }

    /**
     * Indicate the locale of the translation.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }
}
