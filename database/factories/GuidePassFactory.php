<?php

namespace Database\Factories;

use App\Enums\GuidePassStatus;
use App\Models\GuidePass;
use App\Models\Museum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuidePass>
 */
class GuidePassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'museum_id' => Museum::factory()->paid(800),
            'token' => Str::random(40),
            'status' => GuidePassStatus::Pending,
            'amount' => 800,
            'email' => null,
            'stripe_checkout_session_id' => 'cs_test_'.Str::random(24),
            'paid_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the pass is paid and valid for the next 24 hours.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GuidePassStatus::Paid,
            'email' => fake()->safeEmail(),
            'paid_at' => now(),
            'expires_at' => now()->addHours(GuidePass::LIFETIME_HOURS),
        ]);
    }

    /**
     * Indicate that the pass was paid more than 24 hours ago and has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GuidePassStatus::Paid,
            'email' => fake()->safeEmail(),
            'paid_at' => now()->subHours(GuidePass::LIFETIME_HOURS + 1),
            'expires_at' => now()->subHour(),
        ]);
    }
}
