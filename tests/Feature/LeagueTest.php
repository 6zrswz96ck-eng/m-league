<?php
namespace Tests\Feature;
use App\Models\Group;
use App\Models\Player;
use App\Services\MLeagueScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class LeagueTest extends TestCase {
    use RefreshDatabase;
    public function test_group_requires_four_unique_players_and_ranking_uses_current_points(): void {
        $this->app['config']->set('league.admin_password', 'test-secret');
        $players = collect(range(1, 5))->map(fn ($i) => Player::create(['name' => "選手{$i}", 'team_name' => 'チーム', 'season_point' => $i * 10]));
        $this->post('/login', ['username' => 'admin', 'password' => 'test-secret'])->assertRedirect();
        $this->post('/admin/groups', ['name' => 'A', 'players' => [$players[0]->id,$players[1]->id,$players[2]->id]])->assertSessionHasErrors('players');
        $this->post('/admin/groups', ['name' => 'A', 'players' => $players->take(4)->pluck('id')->all()])->assertSessionHasNoErrors();
        $this->assertSame(4, Group::first()->players()->count());
        $this->get('/')->assertOk()->assertSee('+100.0 pt');
        $players[0]->update(['season_point' => -10]);
        $this->get('/')->assertSee('+80.0 pt');
    }
    public function test_admin_login_and_logout(): void {
        $this->app['config']->set('league.admin_password', 'test-secret');
        $this->get('/admin/groups')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('管理者ログイン');
        $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->get('/admin/groups')->assertRedirect('/login');
        $this->post('/login', ['username' => 'admin', 'password' => 'test-secret'])->assertRedirect('/admin/groups');
        $this->get('/admin/groups')->assertOk();
        $this->post('/logout')->assertRedirect('/');
        $this->get('/admin/groups')->assertRedirect('/login');
    }
    public function test_player_introduction_is_public_and_uses_team_color(): void {
        Player::create(['name' => '石井 一馬', 'team_name' => 'EARTH JETS', 'season_point' => 55.6]);
        $this->get('/players')->assertOk()->assertSee('選手紹介')->assertSee('石井 一馬')->assertSee('55.6 pt')->assertSee('#176b4a')->assertSee('https://m-league.jp/teams/jets/');
        $this->get('/admin/players')->assertRedirect('/login');
    }
    public function test_only_lowest_scoring_group_gets_last_place_style(): void {
        $players = collect(range(1, 8))->map(fn ($i) => Player::create(['name' => "Player {$i}", 'team_name' => 'Test', 'season_point' => $i <= 4 ? 10 : -10]));
        $leader = Group::create(['name' => '上位']);
        $leader->players()->sync($players->take(4)->pluck('id'));
        $last = Group::create(['name' => '最下位グループ']);
        $last->players()->sync($players->skip(4)->pluck('id'));
        $response = $this->get('/')->assertOk()->assertSee('最下位');
        $this->assertSame(1, substr_count($response->getContent(), 'rank-card is-last'));
        $this->assertMatchesRegularExpression('/rank-card is-last.*?最下位グループ/s', $response->getContent());
        $response->assertSee('最下位との差 80.0 pt')->assertSee('最下位との差 0.0 pt');
    }
    public function test_ranking_player_link_shows_placements_and_admin_can_correct_them(): void {
        $this->app['config']->set('league.admin_password', 'test-secret');
        $players = collect(range(1, 4))->map(fn ($i) => Player::create(['name' => "選手{$i}", 'team_name' => 'チーム', 'season_point' => 0]));
        $group = Group::create(['name' => 'A']);
        $group->players()->sync($players->pluck('id'));
        $player = $players->first();
        $this->get('/')->assertOk()->assertSee(route('players.show', $player));
        $this->get(route('players.show', $player))->assertSee('次の成績更新後');
        $this->post('/login', ['username' => 'admin', 'password' => 'test-secret'])->assertRedirect();
        $this->put(route('players.update', $player), [
            'name' => '修正した選手', 'team_name' => 'チーム', 'season_point' => '12.3',
            'place_1_count' => 2, 'place_2_count' => 1, 'place_3_count' => 0, 'place_4_count' => 3,
        ])->assertSessionHasNoErrors();
        $this->get(route('players.show', $player))->assertOk()->assertSee('修正した選手')->assertSee('2回')->assertSee('3回');
        $this->get(route('players.manage'))->assertSee('修正した選手')->assertSee('選手の登録・編集');
    }
    public function test_stats_parser_reads_each_placement_count(): void {
        $tables = '';
        foreach (range(1, 10) as $team) {
            $names = implode('', array_map(fn ($i) => '<th scope="col">選手'.$team.'-'.$i.'</th>', range(1, 4)));
            $tables .= '<h2 class="p-stats__teamName">チーム'.$team.'</h2><table class="p-stats__table"><tr>'.$names.'</tr>';
            foreach (['ポイント' => '12.3', '1位' => '2', '2位' => '1', '3位' => '0', '4位' => '3'] as $label => $value) {
                $tables .= '<tr><th>'.$label.'</th>'.str_repeat('<td>'.$value.'</td>', 4).'</tr>';
            }
            $tables .= '</table>';
        }
        $rows = app(MLeagueScoreService::class)->parse('<h1 class="c-title">Stats 2026-27</h1>'.$tables);
        $this->assertCount(40, $rows);
        $this->assertSame([1 => 2, 2 => 1, 3 => 0, 4 => 3], $rows['選手1-1']['placements']);
    }
    public function test_successive_score_updates_record_group_rank_movement(): void {
        foreach (range(1, 40) as $i) {
            Player::create(['name' => "選手{$i}", 'team_name' => 'チーム', 'season_point' => 0]);
        }
        $first = Group::create(['name' => 'A']);
        $first->players()->sync(Player::whereIn('name', ['選手1', '選手2', '選手3', '選手4'])->pluck('id'));
        $second = Group::create(['name' => 'B']);
        $second->players()->sync(Player::whereIn('name', ['選手5', '選手6', '選手7', '選手8'])->pluck('id'));
        $html = function (string $firstScore, string $secondScore): string {
            $body = '<h1 class="c-title">Stats 2026-27</h1>';
            foreach (range(0, 9) as $team) {
                $names = implode('', array_map(fn ($i) => '<th scope="col">選手'.$i.'</th>', range($team * 4 + 1, $team * 4 + 4)));
                $points = implode('', array_map(fn ($i) => '<td>'.($i <= 4 ? $firstScore : ($i <= 8 ? $secondScore : '0')).'</td>', range($team * 4 + 1, $team * 4 + 4)));
                $body .= '<h2 class="p-stats__teamName">チーム</h2><table class="p-stats__table"><tr>'.$names.'</tr><tr><th>ポイント</th>'.$points.'</tr>';
                foreach (range(1, 4) as $place) $body .= '<tr><th>'.$place.'位</th>'.str_repeat('<td>0</td>', 4).'</tr>';
                $body .= '</table>';
            }
            return $body;
        };
        Http::fakeSequence()->push($html('10', '0'))->push($html('0', '10'));
        $service = app(MLeagueScoreService::class);
        $service->update();
        $this->assertNull($first->fresh()->previous_rank);
        $this->assertSame(1, $first->fresh()->last_synced_rank);
        $service->update();
        $this->assertSame(1, $first->fresh()->previous_rank);
        $this->assertSame(2, $first->fresh()->last_synced_rank);
        $this->get('/')->assertOk()->assertSee('↓1')->assertSee('↑1')->assertSee('最下位との差 40.0 pt');
    }
}
