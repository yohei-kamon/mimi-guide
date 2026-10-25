<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\Exhibition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artwork>
 */
class ArtworkFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Artwork $artwork) {
            $defaultLocale = $artwork->exhibition->museum->default_locale;

            if ($artwork->translations()->where('locale', $defaultLocale)->doesntExist()) {
                ArtworkTranslation::factory()->for($artwork)->locale($defaultLocale)->create();
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
            'exhibition_id' => Exhibition::factory(),
            'guide_number' => fake()->unique()->numberBetween(1, 100000),
            'sort_order' => 0,
            'image_path' => null,
            'status' => PublishStatus::Draft,
        ];
    }

    /**
     * Indicate that the artwork is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::Published,
        ]);
    }

    /**
     * Indicate that the artwork was withdrawn after being published.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::Unpublished,
        ]);
    }
}
