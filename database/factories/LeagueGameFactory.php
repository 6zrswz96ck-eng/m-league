<?php

namespace Database\Factories;

use App\Models\LeagueGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeagueGame>
 */
class LeagueGameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_key' => fake()->unique()->sha256(),
            'played_on' => '2026-09-28',
            'round' => 1,
            'teams' => ['チームA', 'チームB', 'チームC', 'チームD'],
            'entries' => array_map(fn (string $team) => ['team_name' => $team, 'player_name' => null, 'rank' => null, 'points' => null], ['チームA', 'チームB', 'チームC', 'チームD']),
            'status' => 'scheduled',
            'last_checked_at' => now(),
        ];
    }
}
