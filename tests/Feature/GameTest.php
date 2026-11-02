<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\LeagueGame;
use App\Models\Player;
use App\Services\MLeagueGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_dropdown_only_lists_published_games_with_weekdays(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Tokyo')->setDate(2026, 9, 30)->setTime(12, 0));
        LeagueGame::factory()->create(['played_on' => '2026-09-29']);
        LeagueGame::factory()->create(['played_on' => '2026-10-01']);
        $this->get('/games')->assertOk()->assertSee('2026/09/29（火）')->assertSee('2026/10/01（木）')
            ->assertDontSee('value="2026-09-30"', false)->assertViewHas('date', '2026-09-29');
        $this->get('/games?date=2026-10-03')->assertDontSee('value="2026-10-03"', false);
        LeagueGame::factory()->create(['played_on' => '2026-10-03']);
        $this->get('/games')->assertSee('2026/10/03（土）');
        LeagueGame::factory()->create(['played_on' => '2026-09-30']);
        $this->get('/games')->assertSee('2026/09/30（水） 今日')->assertViewHas('date', '2026-09-30');
    }

    public function test_games_show_all_current_groups_selecting_each_announced_player(): void
    {
        $player = Player::create(['name' => '出場選手', 'team_name' => 'チームA']);
        $other = Player::create(['name' => '未出場選手', 'team_name' => 'チームB']);
        $first = Group::create(['name' => '益田']);
        $second = Group::create(['name' => '三浦']);
        $unrelated = Group::create(['name' => '未出場のみ選択']);
        $first->players()->attach($player);
        $second->players()->attach($player);
        $unrelated->players()->attach($other);
        $game = LeagueGame::factory()->create(['status' => 'announced', 'entries' => [
            ['player_name' => '出場選手', 'team_name' => 'チームA', 'rank' => null, 'points' => null],
            ['player_name' => null, 'team_name' => 'チームB', 'rank' => null, 'points' => null],
        ]]);
        $url = '/games?date=2026-09-28';
        $this->get($url)->assertOk()->assertSee('この選手を選択中')->assertSee('益田')->assertSee('三浦')->assertDontSee('未出場のみ選択');
        $first->players()->sync([$other->id]);
        $this->get($url)->assertDontSee('益田')->assertSee('三浦');
        $game->update(['status' => 'completed']);
        $this->get($url)->assertSee('三浦');
        $second->players()->detach();
        $this->get($url)->assertDontSee('この選手を選択中');
    }

    public function test_public_date_selection_defaults_to_today_in_japan_and_filters_games(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Tokyo')->setDate(2026, 9, 28)->setTime(1, 0));
        LeagueGame::factory()->create(['played_on' => '2026-09-28']);
        LeagueGame::factory()->create(['played_on' => '2026-09-25', 'status' => 'completed', 'entries' => [
            ['team_name' => 'チームA', 'player_name' => '過去の選手', 'rank' => 4, 'points' => '-42.3'],
        ]]);

        $this->get('/games')->assertOk()->assertSee('出場選手未発表')->assertDontSee('過去の選手');
        $this->get('/games?date=2026-09-25')->assertOk()->assertSee('過去の選手')->assertSee('-42.3 pt')->assertSee('4位');
        $this->get('/games?date=2026-09-26')->assertSee('対局情報');
        $this->get('/games?date=invalid')->assertSessionHasErrors('date');
    }

    public function test_results_and_announcements_are_upserted_without_duplicate_or_downgrading_results(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28));
        $this->players();
        Http::preventStrayRequests();
        Http::fake([
            'https://m-league.jp/api/mleague/data?kind=games' => Http::sequence()
                ->push($this->source(true))->push($this->source(true))->push($this->source(false, false)),
        ]);
        $service = app(MLeagueGameService::class);

        $service->update();
        $service->update();

        $this->assertSame(2, LeagueGame::count());
        $first = LeagueGame::where('round', 1)->firstOrFail();
        $this->assertSame('completed', $first->status);
        $this->assertSame('-42.3', $first->entries[3]['points']);
        $this->assertSame('選手 1', $first->entries[0]['player_name']);
        $this->assertSame('announced', LeagueGame::where('round', 2)->firstOrFail()->status);
        Http::assertSentCount(2);

        $service->update();
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('announced', LeagueGame::where('round', 2)->firstOrFail()->status);
    }

    public function test_invalid_source_preserves_saved_results_and_shows_stale_notice(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28));
        $game = LeagueGame::factory()->create(['status' => 'completed']);
        Cache::forever('games.last_success_at', '2026-09-27T12:00:00+09:00');
        Http::preventStrayRequests();
        Http::fake(['https://m-league.jp/api/mleague/data?kind=games' => Http::response('<html>maintenance</html>')]);

        $this->artisan('mleague:games')->assertExitCode(1);

        $this->assertSame('completed', $game->fresh()->status);
        $this->assertSame('2026-09-27T12:00:00+09:00', Cache::get('games.last_success_at'));
        $this->get('/games')->assertSee('最新の情報を取得できていません');
        Http::assertSentCount(1);
    }

    public function test_multiple_sessions_on_one_day_have_distinct_games_and_unknown_players_are_not_invented(): void
    {
        $this->players();
        foreach (range(5, 8) as $i) {
            Player::create(['name' => '選手 '.$i, 'team_name' => 'チーム'.$i]);
        }
        $first = $this->source(false, false);
        $second = strtr($first, ['チーム1' => 'チーム5', 'チーム2' => 'チーム6', 'チーム3' => 'チーム7', 'チーム4' => 'チーム8']);
        $payload = json_decode($first, true);
        $payload['games'] = array_merge($payload['games'], json_decode($second, true)['games']);
        $service = app(MLeagueGameService::class);

        $rows = $service->parse(json_encode($payload));
        $lineups = $service->parse($first);
        $this->assertCount(4, array_unique(array_column($rows, 'source_key')));
        $this->assertSame('scheduled', $lineups[0]['status']);
        $this->assertNull($lineups[0]['entries'][0]['player_name']);
        $this->assertNull($lineups[0]['entries'][0]['points']);
    }

    public function test_history_fetches_full_season_and_keeps_japan_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1));
        $this->players();
        Http::preventStrayRequests();
        $payload = json_decode($this->source(false, false), true);
        $nextMonth = json_decode(str_replace('2026-09-28T10:00:00.000Z', '2026-09-30T15:00:00.000Z', $this->source(false, false)), true);
        $payload['games'] = array_merge($payload['games'], $nextMonth['games']);
        Http::fake(['https://m-league.jp/api/mleague/data?kind=games' => Http::response($payload)]);
        app(MLeagueGameService::class)->update(true);

        $this->assertSame(2, LeagueGame::whereDate('played_on', '2026-09-28')->count());
        $this->assertSame(2, LeagueGame::whereDate('played_on', '2026-10-01')->count());
        Http::assertSentCount(1);
    }

    public function test_published_players_are_visible_and_tied_results_are_preserved(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Tokyo')->setDate(2026, 9, 28)->setTime(12, 0));
        $this->players();
        Http::preventStrayRequests();
        $payload = json_decode($this->source(true), true);
        $payload['games'][0]['players'][2]['rank'] = 2;
        $payload['games'][0]['players'][2]['point'] = 3.8;
        Http::fake(['https://m-league.jp/api/mleague/data?kind=games' => Http::response($payload)]);

        $this->artisan('mleague:games')->assertExitCode(0);

        $this->get('/games')->assertSee('選手 1')->assertSee('出場選手未発表');
        $first = LeagueGame::where('round', 1)->firstOrFail();
        $this->assertSame(2, $first->entries[2]['rank']);
        $this->assertSame('3.8', $first->entries[2]['points']);
        $second = LeagueGame::where('round', 2)->firstOrFail();
        $this->assertSame('announced', $second->status);
        $this->assertSame('選手 1', $second->entries[0]['player_name']);
        $this->assertNull($second->entries[0]['points']);
        Http::assertSentCount(1);
    }

    public function test_invalid_api_data_does_not_replace_saved_games_or_last_success(): void
    {
        $this->players();
        Http::preventStrayRequests();
        $game = LeagueGame::factory()->create(['status' => 'completed']);
        $savedEntries = $game->entries;
        Cache::forever('games.last_success_at', '2026-09-27T12:00:00+09:00');
        $payload = json_decode($this->source(true), true);
        $invalidPayloads = [
            array_replace_recursive($payload, ['stale' => true]),
            array_replace_recursive($payload, ['season' => ['year' => 2025]]),
            array_replace_recursive($payload, ['games' => [0 => ['date' => '2026-02-30T00:00:00Z']]]),
            array_replace_recursive($payload, ['games' => [0 => ['players' => [0 => ['name' => '不明な選手']]]]]),
            array_replace_recursive($payload, ['games' => [0 => ['players' => [0 => ['point' => null]]]]]),
            array_replace_recursive($payload, ['games' => [0 => ['players' => [0 => ['team' => 'チーム2']]]]]),
        ];
        $missingPlayer = $payload;
        array_pop($missingPlayer['games'][0]['players']);
        $invalidPayloads[] = $missingPlayer;
        $duplicate = $payload;
        $duplicate['games'][] = $payload['games'][0];
        $invalidPayloads[] = $duplicate;
        $sequence = Http::sequence();
        foreach ($invalidPayloads as $invalidPayload) {
            $sequence->push($invalidPayload);
        }
        Http::fake(['https://m-league.jp/api/mleague/data?kind=games' => $sequence]);
        foreach ($invalidPayloads as $invalidPayload) {

            $this->artisan('mleague:games')->assertExitCode(1);

            $this->assertDatabaseCount('league_games', 1);
            $this->assertSame($savedEntries, $game->fresh()->entries);
            $this->assertSame('2026-09-27T12:00:00+09:00', Cache::get('games.last_success_at'));
        }
    }

    private function players(): void
    {
        foreach (range(1, 4) as $i) {
            Player::create(['name' => '選手 '.$i, 'team_name' => 'チーム'.$i]);
        }
    }

    private function source(bool $completed, bool $announced = true): string
    {
        $games = [];
        foreach ([1, 2] as $round) {
            $entries = [];
            foreach ([58, 3.8, -19.5, -42.3] as $index => $point) {
                $number = $index + 1;
                $entries[] = ['team' => 'チーム'.$number,
                    'name' => ($completed && $round === 1) || ($announced && $round === 2 && $number === 1) ? '選手'.$number : null,
                    'point' => $completed && $round === 1 ? $point : null,
                    'rank' => $completed && $round === 1 ? $number : null];
            }
            $games[] = ['date' => '2026-09-28T10:00:00.000Z', 'no' => $round,
                'complete' => $completed && $round === 1, 'players' => $entries];
        }

        return json_encode(['mode' => 'api', 'stale' => false,
            'season' => ['year' => 2026, 'phase' => 'regular', 'name' => '2026-27レギュラーシーズン'],
            'games' => $games], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
