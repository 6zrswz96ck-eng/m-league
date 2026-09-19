<?php
namespace Tests\Feature;
use App\Models\Group;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }
}
