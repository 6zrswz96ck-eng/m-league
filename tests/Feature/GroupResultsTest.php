<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\LeagueGame;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_uses_current_roster_season_points_even_without_match_history(): void
    {
        $group = Group::create(['name' => '総合確認']);
        $players = collect(['100.1', '-20.2', '0.0', '30.4', '-200.0'])->map(fn ($points, $i) => Player::create([
            'name' => '集計選手'.$i, 'team_name' => 'チーム', 'season_point' => $points,
        ]));
        $group->players()->attach($players->take(4)->pluck('id'));
        $url = route('groups.results', ['group' => $group->id]);
        $this->get($url)->assertOk()->assertSee('現在の総合ポイント')->assertSee('+110.3 pt')->assertViewHas('totalTenths', 1103);
        $group->players()->sync($players->skip(1)->pluck('id'));
        $this->get($url)->assertSee('-189.8 pt')->assertViewHas('totalTenths', -1898);
    }

    public function test_public_history_filters_to_current_roster_and_completed_games_in_date_order(): void
    {
        $group = Group::create(['name' => '益田']);
        $players = collect(range(1, 5))->map(fn ($i) => Player::create(['name' => '選手'.$i, 'team_name' => 'チーム'.$i]));
        $group->players()->attach($players->take(4)->pluck('id'));
        $entry = fn ($i, $rank, $points) => ['player_name' => '選手'.$i, 'team_name' => 'チーム'.$i, 'rank' => $rank, 'points' => $points];
        LeagueGame::factory()->create(['played_on' => '2026-09-21', 'status' => 'completed', 'entries' => [$entry(1, 1, '51.2'), $entry(5, 4, '-99.9')]]);
        LeagueGame::factory()->create(['played_on' => '2026-09-25', 'round' => 2, 'status' => 'completed', 'entries' => [$entry(2, 4, '-42.3'), $entry(3, 3, '-12.1')]]);
        LeagueGame::factory()->create(['played_on' => '2026-09-28', 'status' => 'announced', 'entries' => [$entry(4, null, null)]]);
        $url = route('groups.results', ['group' => $group->id]);
        $this->get($url)->assertOk()->assertSee('益田さんの選択選手')->assertSee('選手4')->assertDontSee('選手5')
            ->assertSeeInOrder(['2026/09/25', '4位', '-42.3 pt', '2026/09/21', '1位', '+51.2 pt'])
            ->assertDontSee('2026/09/28')->assertViewHas('results', fn ($results) => $results->count() === 3);
        $group->players()->sync($players->skip(1)->pluck('id'));
        $this->get($url)->assertDontSee('選手1')->assertSee('選手5')->assertSee('-99.9 pt');
    }

    public function test_empty_states_and_invalid_group_selection(): void
    {
        $this->get(route('groups.results'))->assertOk()->assertSee('登録ユーザーがいません');
        $group = Group::create(['name' => '三浦']);
        $this->get(route('groups.results'))->assertSee('ユーザーを選択すると');
        $this->get(route('groups.results', ['group' => $group->id]))->assertSee('確定した対局結果はまだありません');
        $this->get(route('groups.results', ['group' => 99999]))->assertSessionHasErrors('group');
        $this->get(route('groups.results', ['group' => 'invalid']))->assertSessionHasErrors('group');
    }
}
