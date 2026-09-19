<?php
namespace App\Services;

use App\Models\Player;
use App\Models\Group;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MLeagueScoreService {
    private const SOURCE = 'https://m-league.jp/stats/';

    public function update(): int
    {
        try {
            $response = Http::timeout(20)->retry(2, 1000)->get(self::SOURCE);
            $response->throw();
            $rows = $this->parse($response->body());
            $existing = Player::pluck('id', 'name');
            if ($existing->count() < 40 || array_diff(array_keys($rows), $existing->keys()->all())) {
                throw new RuntimeException('選手名または選手数が登録データと一致しません。');
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
                DB::table('sync_states')->updateOrInsert(['id' => 1], ['last_success_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
            });
            return count($rows);
        } catch (\Throwable $e) {
            Log::error('Mリーグ成績更新失敗', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function parse(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        if (!preg_match('/Stats\s*2026-27/u', $xpath->evaluate("string(//h1[contains(concat(' ', normalize-space(@class), ' '), ' c-title ')])"))) {
            throw new RuntimeException('対象シーズンを確認できません。');
        }
        $rows = [];
        foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' p-stats__teamName ')]") as $heading) {
            $team = trim(preg_replace('/\s+/u', ' ', $heading->textContent));
            $table = $xpath->query("following::table[contains(concat(' ', normalize-space(@class), ' '), ' p-stats__table ')][1]", $heading)->item(0);
            if (!$table) throw new RuntimeException('成績表が見つかりません。');
            $names = $xpath->query('./tr[1]/th[@scope="col"]', $table);
            $pointCells = $xpath->query('./tr[th[normalize-space(.)="ポイント"]]/td', $table);
            $placementCells = [];
            foreach (range(1, 4) as $place) {
                $placementCells[$place] = $xpath->query('./tr[th[normalize-space(.)="'.$place.'位"]]/td', $table);
            }
            if ($names->length !== 4 || $pointCells->length !== 4 || collect($placementCells)->contains(fn ($cells) => $cells->length !== 4)) throw new RuntimeException('成績表の列数が不正です。');
            for ($i = 0; $i < 4; $i++) {
                $name = trim(preg_replace('/\s+/u', ' ', $names->item($i)->textContent));
                $raw = trim($pointCells->item($i)->textContent);
                if ($name === '' || isset($rows[$name]) || !preg_match('/^[+-]?\d+(?:\.\d)?$/', $raw) || abs((float)$raw) > 10000 || round((float)$raw, 1) != (float)$raw) {
                    throw new RuntimeException('選手名またはポイント値が不正です。');
                }
                $placements = [];
                foreach (range(1, 4) as $place) {
                    $count = trim($placementCells[$place]->item($i)->textContent);
                    if (!preg_match('/^\d{1,4}$/', $count)) throw new RuntimeException('着順回数が不正です。');
                    $placements[$place] = (int) $count;
                }
                $rows[$name] = ['team' => $team, 'point' => number_format((float)$raw, 1, '.', ''), 'placements' => $placements];
            }
        }
        if (count($rows) !== 40) throw new RuntimeException('40選手分の成績を取得できません。');
        return $rows;
    }
}
