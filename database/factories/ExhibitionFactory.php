<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Exhibition;
use App\Models\ExhibitionTranslation;
use App\Models\Museum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exhibition>
 */
class ExhibitionFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Exhibition $exhibition) {
            $defaultLocale = $exhibition->museum->default_locale;

            if ($exhibition->translations()->where('locale', $defaultLocale)->doesntExist()) {
                ExhibitionTranslation::factory()->for($exhibition)->locale($defaultLocale)->create();
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
            'museum_id' => Museum::factory(),
            'status' => PublishStatus::Draft,
            'starts_on' => null,
            'ends_on' => null,
        ];
    }

    /**
     * Indicate that the exhibition is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::Published,
        ]);
    }

    /**
     * Indicate that the exhibition was withdrawn after being published.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublishStatus::Unpublished,
        ]);
    }

    /**
     * Indicate that the exhibition is a temporary one that has already ended.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => today()->subMonths(3),
            'ends_on' => today()->subDay(),
        ]);
    }
}
