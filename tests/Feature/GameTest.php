<?php

namespace Tests\Feature;

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
            'https://m-league.jp/games/*' => Http::response($this->schedule(true)),
            'https://m-league.jp/' => Http::response($this->lineup()),
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
        Http::assertSentCount(4);

        Http::fake([
            'https://m-league.jp/games/*' => Http::response($this->schedule(false)),
            'https://m-league.jp/' => Http::response('<section class="p-opponentCard"></section>'),
        ]);
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
        Http::fake(['https://m-league.jp/games/*' => Http::response('<html>maintenance</html>')]);

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
        $first = $this->schedule(false);
        $second = strtr($first, ['チーム1' => 'チーム5', 'チーム2' => 'チーム6', 'チーム3' => 'チーム7', 'チーム4' => 'チーム8']);
        $service = app(MLeagueGameService::class);

        $rows = $service->parseSchedule($first.$second, 2026, 9);
        $lineups = $service->parseLineups(str_replace('出場選手 選手1', '出場選手 未定', $this->lineup()));

        $this->assertCount(4, array_unique(array_column($rows, 'source_key')));
        $this->assertSame('scheduled', $lineups[0]['status']);
        $this->assertNull($lineups[0]['entries'][0]['player_name']);
        $this->assertNull($lineups[0]['entries'][0]['points']);
    }

    public function test_history_fetches_months_since_season_start_and_keeps_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1));
        $this->players();
        Http::preventStrayRequests();
        Http::fake([
            'https://m-league.jp/games/?mly=2026&mlm=9' => Http::response($this->schedule(false)),
            'https://m-league.jp/games/?mly=2026&mlm=10' => Http::response(str_replace('9/28', '10/1', $this->schedule(false))),
            'https://m-league.jp/' => Http::response('<section class="p-opponentCard"></section>'),
        ]);

        app(MLeagueGameService::class)->update(true);

        $this->assertSame(2, LeagueGame::whereDate('played_on', '2026-09-28')->count());
        $this->assertSame(2, LeagueGame::whereDate('played_on', '2026-10-01')->count());
        Http::assertSentCount(3);
    }

    private function players(): void
    {
        foreach (range(1, 4) as $i) {
            Player::create(['name' => '選手 '.$i, 'team_name' => 'チーム'.$i]);
        }
    }

    private function lineup(): string
    {
        $items = '<li class="p-opponentCard__item"><img alt="チーム1 出場選手 選手1"></li>';
        foreach (range(2, 4) as $i) {
            $items .= '<li class="p-opponentCard__item"><img alt="チーム'.$i.' 出場選手 未定"></li>';
        }

        return '<section class="p-opponentCard"><section class="p-opponentCard__group"><time datetime="2026-09-28"></time><b class="p-opponentCard__round">第2回戦</b><ul>'.$items.'</ul></section></section>';
    }

    private function schedule(bool $results): string
    {
        $logos = '';
        foreach (range(1, 4) as $i) {
            $logos .= '<li><img alt="チーム'.$i.'"></li>';
        }
        $html = '<ul class="p-gamesSchedule2__lists"><li class="p-gamesSchedule2__list" data-target="key20260928-1"><p class="p-gamesSchedule2__data">9/28（月）</p><ul class="p-gamesSchedule2__logos">'.$logos.'</ul></li></ul>';
        if ($results) {
            $html .= '<div id="js-modal-key20260928-1"><div class="p-gamesResult__column"><div class="p-gamesResult__number">第1回戦</div><ol>';
            foreach (['58pt', '3.8pt', '▲19.5pt', '▲42.3pt'] as $index => $points) {
                $number = $index + 1;
                $html .= '<li class="p-gamesResult__rank-item"><div class="p-gamesResult__rank-badge">'.$number.'</div><div class="p-gamesResult__name">選手'.$number.'</div><div class="p-gamesResult__point">'.$points.'</div></li>';
            }
            $html .= '</ol></div></div>';
        }

        return $html;
    }
}
