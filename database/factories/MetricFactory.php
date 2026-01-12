<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Metric>
 */
class MetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'value' => fake()->numberBetween(100, 10000),
            'model' => fake()->randomElement(['claude-sonnet-4-20250514', 'claude-opus-4-20250514', 'claude-haiku-3-5-20241022']),
            'session_id' => fake()->uuid(),
            'recorded_at' => now(),
        ];
    }

    public function tokensInput(?int $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Metric::TYPE_TOKENS_INPUT,
            'value' => $value ?? fake()->numberBetween(1000, 50000),
        ]);
    }

    public function tokensOutput(?int $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Metric::TYPE_TOKENS_OUTPUT,
            'value' => $value ?? fake()->numberBetween(500, 20000),
        ]);
    }

    public function cost(?float $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Metric::TYPE_COST,
            'value' => $value ?? fake()->randomFloat(4, 0.01, 5.00),
        ]);
    }

    public function linesAdded(?int $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Metric::TYPE_LINES_ADDED,
            'value' => $value ?? fake()->numberBetween(10, 500),
        ]);
    }

    public function linesRemoved(?int $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Metric::TYPE_LINES_REMOVED,
            'value' => $value ?? fake()->numberBetween(5, 200),
        ]);
    }

    public function commits(?int $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Metric::TYPE_COMMITS,
            'value' => $value ?? fake()->numberBetween(1, 20),
        ]);
    }

    public function recordedAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'recorded_at' => $date,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
