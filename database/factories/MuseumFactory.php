<?php

namespace Database\Factories;

use App\Models\Museum;
use App\Models\MuseumTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Museum>
 */
class MuseumFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Museum $museum) {
            if ($museum->translations()->where('locale', $museum->default_locale)->doesntExist()) {
                MuseumTranslation::factory()->for($museum)->create([
                    'locale' => $museum->default_locale,
                ]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'locales' => ['ja'],
            'default_locale' => 'ja',
            'guide_price' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the museum is stopped and hidden from visitors.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the museum charges for the guide.
     */
    public function paid(int $price = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'guide_price' => $price,
        ]);
    }
}
