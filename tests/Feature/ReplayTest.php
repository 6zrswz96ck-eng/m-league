<?php

namespace Tests\Feature;

use App\Models\LeagueGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_table_and_round_links_to_its_exact_replay_and_unknown_games_have_no_link(): void
    {
        Http::preventStrayRequests();
        $html = '';
        $expected = [];
        foreach (['A', 'B'] as $table) {
            foreach ([1, 2] as $round) {
                $names = array_map(fn ($i) => $table.$round.'選手'.$i, range(1, 4));
                $game = LeagueGame::factory()->create(['played_on' => '2026-09-21', 'round' => $round, 'status' => 'completed', 'entries' => array_map(fn ($name) => ['player_name' => $name, 'team_name' => 'チーム', 'rank' => 1, 'points' => '10.0'], $names)]);
                $path = '/seasons/2026-27/regular/09-21/'.$table.'-'.$round.'/';
                $expected[$game->id] = 'https://m-league.konoui.dev'.$path;
                $html .= '<a href="'.$path.'"><span class="players">'.implode(' vs ', array_reverse($names)).'</span></a>';
            }
        }
        Http::fake(['m-league.konoui.dev/*' => Http::response($html)]);
        $this->get('/games?date=2026-09-21')->assertOk()->assertViewHas('replayLinks', $expected)->assertSee('この対局の牌譜を開く');
        $this->get('/games?date=2026-09-21')->assertOk();
        Http::assertSentCount(1);
        $game->update(['played_on' => '2026-09-22']);
        $this->get('/games?date=2026-09-22')->assertViewHas('replayLinks', [])->assertSee('まだ確認できません');
    }

    public function test_unavailable_replay_source_does_not_break_results(): void
    {
        Http::preventStrayRequests();
        Http::fake(['m-league.konoui.dev/*' => Http::response('', 503)]);
        LeagueGame::factory()->create(['entries' => array_map(fn ($i) => ['player_name' => '選手'.$i, 'team_name' => 'チーム', 'rank' => $i, 'points' => '1.0'], range(1, 4))]);
        $this->get('/games?date=2026-09-28')->assertOk()->assertSee('選手1')->assertSee('まだ確認できません')->assertViewHas('replayLinks', []);
    }
}
