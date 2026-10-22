<?php

namespace Database\Factories;

use App\Models\Artwork;
use App\Models\AudioGuide;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AudioGuide>
 */
class AudioGuideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Only the record is created. Put a file at `path` with `Storage::fake()` when the test reads it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artwork_id' => Artwork::factory(),
            'locale' => 'ja',
            'path' => 'audio/'.fake()->randomNumber().'/'.Str::random(40).'.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'duration_seconds' => null,
        ];
    }

    /**
     * Indicate the locale of the audio guide.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }
}
