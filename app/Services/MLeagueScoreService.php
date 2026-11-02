<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Group;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MLeagueScoreService
{
    private const SOURCE = 'https://m-league.jp/api/mleague/data?kind=stats';

    public function update(): int
    {
        try {
            $response = Http::connectTimeout(5)->timeout(30)->retry(2, 1000)->get(self::SOURCE);
            $response->throw();
            $rows = $this->parse($response->body());
            $existing = Player::pluck('id', 'name');
            if ($existing->count() < 40 || array_diff(array_keys($rows), $existing->keys()->all())) {
                throw new RuntimeException('選手名または選手数が登録データと一致しません。');
            }
            $hasResults = collect($rows)->contains(fn (array $row): bool => (float) $row['point'] !== 0.0
                || array_sum($row['placements']) > 0);
            $hasSavedResults = Player::where('season_point', '!=', 0)
                ->orWhere('place_1_count', '>', 0)
                ->orWhere('place_2_count', '>', 0)
                ->orWhere('place_3_count', '>', 0)
                ->orWhere('place_4_count', '>', 0)
                ->exists();
            if (! $hasResults && $hasSavedResults) {
                throw new RuntimeException('公式成績が全選手0のため、保存済みの成績を保持します。');
            }
            DB::transaction(function () use ($rows): void {
                foreach ($rows as $name => $row) {
                    Player::where('name', $name)->update([
                        'team_name' => $row['team'],
                        'season_point' => $row['point'],
                        'place_1_count' => $row['placements'][1],
                        'place_2_count' => $row['placements'][2],
                        'place_3_count' => $row['placements'][3],
                        'place_4_count' => $row['placements'][4],
                    ]);
                }
                $groups = Group::with('players')->get()->sortByDesc(
                    fn ($group) => $group->players->sum(fn ($player) => (int) round((float) $player->season_point * 10))
                )->values();
                $previousTotal = null;
                $rank = 0;
                foreach ($groups as $index => $group) {
                    $total = $group->players->sum(fn ($player) => (int) round((float) $player->season_point * 10));
                    $rank = $total === $previousTotal ? $rank : $index + 1;
                    $group->update(['previous_rank' => $group->last_synced_rank, 'last_synced_rank' => $rank]);
                    $previousTotal = $total;
                }
                foreach (Category::with('groups.players')->get() as $category) {
                    $groups = $category->groups->sortByDesc(
                        fn ($group) => $group->players->sum(fn ($player) => (int) round((float) $player->season_point * 10))
                    )->values();
                    $previousTotal = null;
                    $rank = 0;
                    foreach ($groups as $index => $group) {
                        $total = $group->players->sum(fn ($player) => (int) round((float) $player->season_point * 10));
                        $rank = $total === $previousTotal ? $rank : $index + 1;
                        $category->groups()->updateExistingPivot($group->id, [
                            'previous_rank' => $group->pivot->last_synced_rank,
                            'last_synced_rank' => $rank,
                        ]);
                        $previousTotal = $total;
                    }
                }
                DB::table('sync_states')->updateOrInsert(['id' => 1], ['last_success_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
            });

            return count($rows);
        } catch (\Throwable $e) {
            Log::error('Mリーグ成績更新失敗', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @return array<string, array{team: string, point: string, placements: array<int, int>}> */
    public function parse(string $json): array
    {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $season = config('league.season');
        if (($payload['season']['year'] ?? null) !== (int) substr($season, 0, 4)
            || ($payload['season']['phase'] ?? null) !== 'regular'
            || ! str_contains($payload['season']['name'] ?? '', $season)
            || ! in_array($payload['mode'] ?? null, ['api', 'manual'], true)
            || ($payload['stale'] ?? true) !== false) {
            throw new RuntimeException('最新の対象シーズンの成績を確認できません。');
        }
        $teams = $payload['mode'] === 'manual' ? ($payload['data']['teams'] ?? null) : ($payload['stats']['teams'] ?? null);
        if (! is_array($teams) || count($teams) !== 10) {
            throw new RuntimeException('10チーム分の成績を取得できません。');
        }
        $rows = [];
        foreach ($teams as $team) {
            $teamName = trim(preg_replace('/\s+/u', ' ', $team['team'] ?? ''));
            $names = $team['players'] ?? [];
            $metrics = collect($team['metrics'] ?? [])->keyBy('name');
            if ($teamName === '' || ! is_array($names) || count($names) !== 4 || ! array_is_list($names)) {
                throw new RuntimeException('チーム名または選手数が不正です。');
            }
            foreach (['ポイント', '1位', '2位', '3位', '4位'] as $label) {
                $values = $metrics->get($label)['values'] ?? null;
                if (! is_array($values) || count($values) !== 4 || ! array_is_list($values)) {
                    throw new RuntimeException('成績表の列数が不正です。');
                }
            }
            foreach ($names as $index => $name) {
                $name = trim(preg_replace('/\s+/u', ' ', $name));
                $point = $metrics->get('ポイント')['values'][$index];
                if ($name === '' || isset($rows[$name]) || ! is_numeric($point)
                    || abs((float) $point) > 10000 || round((float) $point, 1) != (float) $point) {
                    throw new RuntimeException('選手名またはポイント値が不正です。');
                }
                $placements = [];
                foreach (range(1, 4) as $place) {
                    $count = $metrics->get($place.'位')['values'][$index];
                    if (! is_int($count) || $count < 0 || $count > 9999) {
                        throw new RuntimeException('着順回数が不正です。');
                    }
                    $placements[$place] = $count;
                }
                $rows[$name] = ['team' => $teamName, 'point' => number_format((float) $point, 1, '.', ''), 'placements' => $placements];
            }
        }
        if (count($rows) !== 40) {
            throw new RuntimeException('40選手分の成績を取得できません。');
        }

        return $rows;
    }
}
