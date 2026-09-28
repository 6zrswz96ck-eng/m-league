<?php

namespace App\Services;

use App\Models\LeagueGame;
use App\Models\Player;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
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
            $startYear = (int) substr(config('league.season'), 0, 4);
            $start = CarbonImmutable::create($startYear, 9, 1, 0, 0, 0, 'Asia/Tokyo');
            $end = $start->addMonths(8);
            $month = CarbonImmutable::now('Asia/Tokyo')->startOfMonth()->max($start)->min($end);
            $cursor = $history || ! LeagueGame::exists() ? $start : $month;
            $rows = [];
            do {
                $html = Http::connectTimeout(5)->timeout(25)->get('https://m-league.jp/games/', [
                    'mly' => $cursor->year, 'mlm' => $cursor->month,
                ])->throw()->body();
                $rows = array_merge($rows, $this->parseSchedule($html, $cursor->year, $cursor->month));
                $cursor = $cursor->addMonth();
            } while ($cursor <= $month);

            $home = Http::connectTimeout(5)->timeout(25)->get('https://m-league.jp/')->throw()->body();
            $rows = array_merge($rows, $this->parseLineups($home));
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

    public function parseSchedule(string $html, int $year, int $month): array
    {
        $xpath = $this->document($html);
        if ($this->nodes($xpath, 'p-gamesSchedule2__lists')->length === 0) {
            throw new RuntimeException('公式日程ページの形式を確認できません。');
        }
        $rows = [];
        $players = Player::all()->keyBy(fn (Player $player) => $this->normalize($player->name));
        $teamNames = $players->pluck('team_name')->unique()->keyBy(fn (string $team) => $this->normalize($team));
        foreach ($this->nodes($xpath, 'p-gamesSchedule2__list') as $session) {
            $dateText = $this->text($xpath, 'p-gamesSchedule2__data', $session);
            if (! preg_match('~^(\d{1,2})/(\d{1,2})~u', $dateText, $dateParts)
                || (int) $dateParts[1] !== $month || ! checkdate($month, (int) $dateParts[2], $year)) {
                throw new RuntimeException('公式日程の日付が不正です。');
            }
            $date = sprintf('%04d-%02d-%02d', $year, $month, $dateParts[2]);
            $teams = [];
            foreach ($this->nodes($xpath, 'p-gamesSchedule2__logos', $session) as $logos) {
                foreach ($xpath->query('.//img', $logos) as $image) {
                    $name = trim($image->getAttribute('alt'));
                    $teams[] = $teamNames->get($this->normalize($name), $name);
                }
            }
            $this->validateTeams($teams);
            $target = $session->getAttribute('data-target');
            $modal = preg_match('/^key\d{8}-\d+$/', $target)
                ? $xpath->query('//*[@id="js-modal-'.$target.'"]')->item(0) : null;
            $results = [];
            if ($modal) {
                foreach ($this->nodes($xpath, 'p-gamesResult__column', $modal) as $column) {
                    $label = $this->text($xpath, 'p-gamesResult__number', $column);
                    if (! preg_match('/第([12])回戦/u', $label, $number)) {
                        throw new RuntimeException('公式結果の対局番号が不正です。');
                    }
                    $entries = [];
                    foreach ($this->nodes($xpath, 'p-gamesResult__rank-item', $column) as $item) {
                        $name = $this->text($xpath, 'p-gamesResult__name', $item);
                        $player = $players->get($this->normalize($name));
                        $points = str_replace(['▲', '△', '−', 'pt', ','], ['-', '-', '-', '', ''], $this->text($xpath, 'p-gamesResult__point', $item));
                        $rank = $this->text($xpath, 'p-gamesResult__rank-badge', $item);
                        if (! $player || ! in_array($player->team_name, $teams, true)
                            || ! preg_match('/^[+-]?\d+(?:\.\d)?$/', $points) || ! preg_match('/^[1-4]$/', $rank)) {
                            throw new RuntimeException('公式対局結果を正しく読み取れません。');
                        }
                        $entries[] = ['player_name' => $player->name, 'team_name' => $player->team_name,
                            'rank' => (int) $rank, 'points' => number_format((float) $points, 1, '.', '')];
                    }
                    if (count($entries) !== 4 || count(array_unique(array_column($entries, 'team_name'))) !== 4) {
                        throw new RuntimeException('対局結果が4人分ありません。');
                    }
                    $results[(int) $number[1]] = $entries;
                }
            }
            foreach ([1, 2] as $round) {
                $rows[] = $this->row($date, $round, $teams, $results[$round] ?? $this->emptyEntries($teams), isset($results[$round]) ? 'completed' : 'scheduled');
            }
        }

        return $rows;
    }

    public function parseLineups(string $html): array
    {
        $xpath = $this->document($html);
        if ($this->nodes($xpath, 'p-opponentCard')->length === 0) {
            throw new RuntimeException('公式出場予定の形式を確認できません。');
        }
        $players = Player::all();
        $teams = $players->pluck('team_name')->unique();
        $rows = [];
        foreach ($this->nodes($xpath, 'p-opponentCard__group') as $group) {
            $date = $xpath->evaluate('string(.//time/@datetime)', $group);
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
                || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw new RuntimeException('出場予定の日付が不正です。');
            }
            $seasonYear = (int) substr(config('league.season'), 0, 4);
            if ($date < $seasonYear.'-09-01' || $date > ($seasonYear + 1).'-05-31') {
                continue;
            }
            if (! preg_match('/第([12])回戦/u', $this->text($xpath, 'p-opponentCard__round', $group), $round)) {
                throw new RuntimeException('出場予定の対局番号が不正です。');
            }
            $entries = [];
            foreach ($this->nodes($xpath, 'p-opponentCard__item', $group) as $item) {
                $text = $item->textContent;
                foreach ($xpath->query('.//img', $item) as $image) {
                    $text .= ' '.$image->getAttribute('alt');
                }
                $normalized = $this->normalize($text);
                $player = $players->first(fn (Player $player) => str_contains($normalized, $this->normalize($player->name)));
                $team = $player?->team_name ?? $teams->first(fn (string $team) => str_contains($normalized, $this->normalize($team)));
                if (! $team) {
                    throw new RuntimeException('出場予定の所属チームが不明です。');
                }
                $entries[] = ['player_name' => $player?->name, 'team_name' => $team, 'rank' => null, 'points' => null];
            }
            $lineupTeams = array_column($entries, 'team_name');
            $this->validateTeams($lineupTeams);
            $announced = count(array_filter(array_column($entries, 'player_name'))) > 0;
            $rows[] = $this->row($date, (int) $round[1], $lineupTeams, $entries, $announced ? 'announced' : 'scheduled');
        }

        return $rows;
    }

    private function validateTeams(array $teams): void
    {
        if (count($teams) !== 4 || count(array_unique($teams)) !== 4 || in_array('', $teams, true)) {
            throw new RuntimeException('対局のチーム構成が不正です。');
        }
    }

    private function emptyEntries(array $teams): array
    {
        return array_map(fn (string $team) => ['player_name' => null, 'team_name' => $team, 'rank' => null, 'points' => null], $teams);
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

    private function document(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);

            return new DOMXPath($dom);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function nodes(DOMXPath $xpath, string $class, ?DOMNode $context = null): DOMNodeList
    {
        return $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]", $context);
    }

    private function text(DOMXPath $xpath, string $class, DOMNode $context): string
    {
        return preg_replace('/\s+/u', '', $this->nodes($xpath, $class, $context)->item(0)?->textContent ?? '');
    }
}
