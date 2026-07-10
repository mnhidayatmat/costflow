<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WccRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WccRecord>
 */
class WccRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(8_000, 50_000);

        return [
            'quo_no' => 'BPE-Q-'.fake()->unique()->numberBetween(2500, 2699),
            'client' => fake()->company(),
            'title' => fake()->sentence(4),
            'dept' => fake()->randomElement(config('costflow.departments')),
            'manager' => strtoupper(fake()->firstName()),
            'planned_cost' => $cost,
            'selling' => round($cost * 1.45, 2),
            'actual' => 0,
            'status' => WccRecord::DRAFT,
            'created_by' => User::factory()->engineer(),
            'snapshot' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WccRecord::APPROVED,
            'approved_at' => now(),
            'actual' => round($attributes['planned_cost'] * 0.98, 2),
        ]);
    }
}
