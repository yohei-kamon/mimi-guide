<?php

namespace Database\Factories;

use App\Models\ArtworkTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArtworkTranslation>
 */
class ArtworkTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The artwork is not created here because ArtworkFactory already adds the
     * default-locale translation. Use `->for($artwork)` to attach one, and
     * pick a non-default locale with `locale()` to avoid the unique constraint.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'ja',
            'title' => fake()->randomElement(['富士', '月', '波', '花鳥', '山水']).'図',
            'artist' => fake()->name(),
            'production_year' => fake()->numberBetween(1600, 1900).'年頃',
            'description' => fake()->realText(200),
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
