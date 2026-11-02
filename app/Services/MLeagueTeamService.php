<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MLeagueTeamService
{
    public function update(): int
    {
        try {
            $json = Http::connectTimeout(5)->timeout(25)->get('https://m-league.jp/api/mleague/data?kind=ranking')->throw()->body();
            $teams = $this->parse($json);
            Cache::forever('teams.ranking', ['teams' => $teams, 'updated_at' => now()->toIso8601String()]);
            Cache::forget('teams.sync_error');

            return count($teams);
        } catch (\Throwable $exception) {
            Cache::forever('teams.sync_error', true);
            throw $exception;
        }
    }

    /** @return array<int, array{name: string, rank: int, points: int, games: string}> */
    public function parse(string $json): array
    {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $season = config('league.season');
        if (($payload['season']['year'] ?? null) !== (int) substr($season, 0, 4)
            || ($payload['season']['phase'] ?? null) !== 'regular'
            || ! str_contains($payload['season']['name'] ?? '', $season)
            || ($payload['stale'] ?? true) !== false
            || ! in_array($payload['mode'] ?? null, ['api', 'manual'], true)) {
            throw new RuntimeException('最新の対象シーズンのランキングを確認できません。');
        }
        $source = $payload['mode'] === 'manual' ? ($payload['data']['teams'] ?? []) : ($payload['ranking']['teams'] ?? []);
        if (! is_array($source) || count($source) !== 10) {
            throw new RuntimeException('10チーム分のランキングを取得できません。');
        }
        $teams = [];
        foreach ($source as $team) {
            $name = trim($team['name'] ?? '');
            $name = $name === 'KONAMI麻雀格闘倶楽部' ? 'KONAMI 麻雀格闘倶楽部' : $name;
            $point = $team['points'] ?? null;
            $games = $team['games'] ?? null;
            if ($name === '' || isset($teams[$name]) || ! is_numeric($point)
                || abs((float) $point) > 10000 || round((float) $point, 1) != (float) $point
                || ! is_int($games) || $games < 0 || $games > 200) {
                throw new RuntimeException('公式チームランキングを読み取れません。');
            }
            $teams[$name] = ['name' => $name, 'rank' => 0, 'points' => (int) round((float) $point * 10), 'games' => (string) $games];
        }
        $teams = collect($teams)->sortByDesc('points')->values()->all();
        $previousPoints = null;
        $rank = 0;
        foreach ($teams as $index => &$team) {
            $rank = $team['points'] === $previousPoints ? $rank : $index + 1;
            $team['rank'] = $rank;
            $previousPoints = $team['points'];
        }
        unset($team);

        return $teams;
    }
}
