<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MLeagueTeamService
{
    public function update(): int
    {
        try {
            $html = Http::connectTimeout(5)->timeout(25)->get('https://m-league.jp/')->throw()->body();
            $teams = $this->parse($html);
            Cache::forever('teams.ranking', ['teams' => $teams, 'updated_at' => now()->toIso8601String()]);
            Cache::forget('teams.sync_error');

            return count($teams);
        } catch (\Throwable $exception) {
            Cache::forever('teams.sync_error', true);
            throw $exception;
        }
    }

    /** @return array<int, array{name: string, rank: int, points: int, games: string}> */
    public function parse(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new DOMXPath($dom);
            $class = fn (string $name) => "contains(concat(' ', normalize-space(@class), ' '), ' {$name} ')";
            $items = $xpath->query('//*['.$class('p-ranking__team-list').' and '.$class('-regular').']//*['.$class('p-ranking__team-item').']');
            $teams = [];
            foreach ($items as $item) {
                $value = fn (string $name) => trim($xpath->evaluate('string(.//*['.$class($name).'])', $item));
                $name = trim($xpath->evaluate('string(.//*['.$class('p-ranking__team-symbol').']//img/@alt)', $item));
                $name = $name === 'KONAMI麻雀格闘倶楽部' ? 'KONAMI 麻雀格闘倶楽部' : $name;
                $point = str_replace(['pt', '▲', '−', ','], ['', '-', '-', ''], $value('p-ranking__current-point'));
                $rank = $value('p-ranking__rank-number');
                $games = preg_replace('/\s+/u', '', $value('p-ranking__game-count'));
                if ($name === '' || isset($teams[$name]) || ! preg_match('/^[+-]?\d+(?:\.\d)?$/', $point)
                    || ! preg_match('/^(?:[1-9]|10)$/', $rank) || ! preg_match('~^\d+/\d+$~', $games)) {
                    throw new RuntimeException('公式チームランキングを読み取れません。');
                }
                $teams[$name] = ['name' => $name, 'rank' => (int) $rank, 'points' => (int) round((float) $point * 10), 'games' => $games];
            }
            if (count($teams) !== 10) {
                throw new RuntimeException('10チーム分のランキングを取得できません。');
            }

            return collect($teams)->sortBy('rank')->values()->all();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
