<?php

namespace App\Services;

use App\Models\LeagueGame;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MLeagueGameService
{
    public function update(bool $history = false): int
    {
        $lock = Cache::lock('games.sync', 600);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $json = Http::connectTimeout(5)->timeout(30)
                ->get('https://m-league.jp/api/mleague/data?kind=games')->throw()->body();
            $rows = $this->parse($json);
            DB::transaction(function () use ($rows): void {
                foreach ($rows as $row) {
                    $existing = LeagueGame::where('source_key', $row['source_key'])->first();
                    if ($existing && ($existing->status === 'completed' && $row['status'] !== 'completed'
                        || $existing->status === 'announced' && $row['status'] === 'scheduled')) {
                        continue;
                    }
                    LeagueGame::updateOrCreate(['source_key' => $row['source_key']], [
                        ...$row, 'last_checked_at' => now(),
                    ]);
                }
            });
            Cache::forever('games.last_success_at', now()->toIso8601String());
            Cache::forget('games.sync_error');

            return count($rows);
        } catch (\Throwable $exception) {
            Cache::forever('games.sync_error', true);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function parse(string $json): array
    {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $season = config('league.season');
        $seasonYear = (int) substr($season, 0, 4);
        if (($payload['season']['year'] ?? null) !== $seasonYear
            || ($payload['season']['phase'] ?? null) !== 'regular'
            || ! str_contains($payload['season']['name'] ?? '', $season)
            || ($payload['stale'] ?? true) !== false
            || ! in_array($payload['mode'] ?? null, ['api', 'manual'], true)) {
            throw new RuntimeException('最新の対象シーズンの対局情報を確認できません。');
        }
        $games = $payload['mode'] === 'manual' ? ($payload['data']['games'] ?? null) : ($payload['games'] ?? null);
        if (! is_array($games) || $games === []) {
            throw new RuntimeException('公式対局情報を取得できません。');
        }
        $players = Player::all()->keyBy(fn (Player $player): string => $this->normalize($player->name));
        $teamNames = $players->pluck('team_name')->unique()->keyBy(fn (string $team): string => $this->normalize($team));
        $rows = [];
        foreach ($games as $game) {
            $rawDate = $game['date'] ?? '';
            if (! is_string($rawDate) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})T/', $rawDate, $parts)
                || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw new RuntimeException('公式対局の日付が不正です。');
            }
            $date = CarbonImmutable::parse($rawDate)->setTimezone('Asia/Tokyo')->toDateString();
            if ($date < $seasonYear.'-09-01' || $date > ($seasonYear + 1).'-05-31'
                || ! in_array($game['no'] ?? null, [1, 2], true) || ! is_bool($game['complete'] ?? null)
                || ! is_array($game['players'] ?? null) || count($game['players']) !== 4) {
                throw new RuntimeException('公式対局の構成が不正です。');
            }
            $entries = [];
            foreach ($game['players'] as $entry) {
                $team = $teamNames->get($this->normalize($entry['team'] ?? ''));
                $rawName = $entry['name'] ?? null;
                $player = $rawName ? $players->get($this->normalize($rawName)) : null;
                if (! $team || ($rawName && ! $player) || ($player && $player->team_name !== $team)) {
                    throw new RuntimeException('対局の選手または所属チームが不明です。');
                }
                $point = $entry['point'] ?? null;
                $rank = $entry['rank'] ?? null;
                if ($game['complete'] && (! $player || ! is_numeric($point)
                    || abs((float) $point) > 1000 || round((float) $point, 1) != (float) $point
                    || ! in_array($rank, [1, 2, 3, 4], true))) {
                    throw new RuntimeException('公式対局結果を正しく読み取れません。');
                }
                $entries[] = ['player_name' => $player?->name, 'team_name' => $team,
                    'rank' => $game['complete'] ? $rank : null,
                    'points' => $game['complete'] ? number_format((float) $point, 1, '.', '') : null];
            }
            $teams = array_column($entries, 'team_name');
            if (count(array_unique($teams)) !== 4) {
                throw new RuntimeException('対局のチーム構成が不正です。');
            }
            $announced = count(array_filter(array_column($entries, 'player_name'))) > 0;
            $row = $this->row($date, $game['no'], $teams, $entries, $game['complete'] ? 'completed' : ($announced ? 'announced' : 'scheduled'));
            if (isset($rows[$row['source_key']])) {
                throw new RuntimeException('公式対局情報が重複しています。');
            }
            $rows[$row['source_key']] = $row;
        }

        return array_values($rows);
    }

    private function row(string $date, int $round, array $teams, array $entries, string $status): array
    {
        sort($teams, SORT_STRING);

        return ['source_key' => hash('sha256', $date.'|'.$round.'|'.implode('|', $teams)),
            'played_on' => $date, 'round' => $round, 'teams' => $teams, 'entries' => $entries, 'status' => $status];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/u', '', $value);
    }
}
