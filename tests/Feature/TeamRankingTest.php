<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\MLeagueTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_ranking_links_to_public_roster_with_each_player_point(): void
    {
        Cache::forever('teams.ranking', ['updated_at' => '2026-10-02T12:00:00+09:00', 'teams' => [[
            'name' => 'KONAMI 麻雀格闘倶楽部', 'rank' => 3, 'points' => 432, 'games' => '12/120',
        ]]]);
        foreach ([['佐々木寿人', '25.5'], ['伊達朱里紗', '10.0'], ['滝沢和典', '-2.3'], ['高宮まり', '-8.1']] as [$name, $points]) {
            Player::create(['name' => $name, 'team_name' => 'KONAMI 麻雀格闘倶楽部', 'season_point' => $points]);
        }
        Player::create(['name' => '別チーム選手', 'team_name' => '別チーム', 'season_point' => '999.9']);

        $url = route('teams.show', ['team' => 'KONAMI 麻雀格闘倶楽部']);
        $this->get(route('teams.ranking'))->assertOk()->assertSee($url)
            ->assertSeeInOrder(['構成選手を見る', '試合数 12/120', '1つ上との差']);
        $this->get($url)->assertOk()->assertSee('現在のチームポイント')->assertSee('+43.2 pt')->assertSee('3位')
            ->assertSeeInOrder(['佐々木寿人', '+25.5 pt', '伊達朱里紗', '+10.0 pt', '滝沢和典', '-2.3 pt', '高宮まり', '-8.1 pt'])
            ->assertDontSee('別チーム選手');
        $this->get(route('players.show', ['player' => Player::where('name', '佐々木寿人')->firstOrFail(), 'return' => '/teams/details?team='.urlencode('KONAMI 麻雀格闘倶楽部')]))
            ->assertOk()->assertSee('チーム構成に戻る');
    }

    public function test_unknown_team_detail_is_not_exposed(): void
    {
        $this->get('/teams/details')->assertSessionHasErrors('team');
        $this->get(route('teams.show', ['team' => '存在しないチーム']))->assertNotFound();
    }

    public function test_official_teams_are_public_with_previous_team_gap_and_sixth_place_border(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://m-league.jp/' => Http::response($this->source())]);
        $this->artisan('mleague:teams')->assertExitCode(0);
        $this->get('/teams/ranking')->assertOk()->assertSee('KONAMI 麻雀格闘倶楽部')
            ->assertSee('+100.0 pt')->assertSee('-80.0 pt')->assertSee('1つ上との差 —')->assertSee('1つ上との差 20.0 pt')->assertDontSee('最下位との差')
            ->assertSee('10/120')->assertSee('#7a1025')
            ->assertSeeInOrder(['1位', '2位', '3位', '6位', 'ボーダー順位', '6位ボーダー', '7位', '10位']);
        $this->assertCount(10, Cache::get('teams.ranking')['teams']);
    }

    public function test_missing_or_invalid_source_preserves_last_good_snapshot(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://m-league.jp/' => Http::sequence()->push($this->source())->push($this->source(9))->push('unavailable', 503)]);
        app(MLeagueTeamService::class)->update();
        $saved = Cache::get('teams.ranking');
        $this->artisan('mleague:teams')->assertExitCode(1);
        $this->assertSame($saved, Cache::get('teams.ranking'));
        $this->get('/teams/ranking')->assertSee('最新情報を取得できていません')->assertSee('+100.0 pt');
        $this->artisan('mleague:teams')->assertExitCode(1);
        $this->assertSame($saved, Cache::get('teams.ranking'));
    }

    public function test_uninitialized_page_does_not_invent_zero_scores(): void
    {
        $this->get('/teams/ranking')->assertOk()->assertSee('取得準備中')->assertDontSee('0.0 pt');
    }

    public function test_adjacent_tied_teams_have_zero_gap(): void
    {
        Cache::forever('teams.ranking', ['updated_at' => now()->toIso8601String(), 'teams' => [
            ['name' => 'チームA', 'rank' => 1, 'points' => 123, 'games' => '1/120'],
            ['name' => 'チームB', 'rank' => 1, 'points' => 123, 'games' => '1/120'],
            ['name' => 'チームC', 'rank' => 3, 'points' => -14, 'games' => '1/120'],
        ]]);
        $this->get('/teams/ranking')->assertOk()->assertSee('1つ上との差 0.0 pt')->assertSee('1つ上との差 13.7 pt');
    }

    private function source(int $count = 10): string
    {
        $html = '<ol class="p-ranking__team-list -regular">';
        foreach (range(1, $count) as $rank) {
            $name = $rank === 1 ? 'KONAMI麻雀格闘倶楽部' : 'チーム'.$rank;
            $html .= '<li class="p-ranking__team-item"><span class="p-ranking__rank-number">'.$rank.'</span><div class="p-ranking__team-symbol"><img alt="'.$name.'"></div><span class="p-ranking__current-point">'.(120 - $rank * 20).'pt</span><span class="p-ranking__game-count">10/120</span></li>';
        }

        return $html.'</ol>';
    }
}
