<?php

namespace Database\Factories;

use App\Models\ExhibitionTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExhibitionTranslation>
 */
class ExhibitionTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The exhibition is not created here because ExhibitionFactory already adds the
     * default-locale translation. Use `->for($exhibition)` to attach one, and
     * pick a non-default locale with `locale()` to avoid the unique constraint.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'ja',
            'name' => fake()->randomElement(['常設展', '企画展', '特別展']).' '.fake()->word(),
            'description' => fake()->realText(100),
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
