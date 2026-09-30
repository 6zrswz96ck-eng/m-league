<?php

namespace App\Services;

use App\Models\LeagueGame;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MLeagueReplayService
{
    /** @return array<int, array<string, string>> */
    public function seats(Collection $games, array $links): array
    {
        $seats = [];
        foreach ($games as $game) {
            if (! isset($links[$game->id])) {
                continue;
            }
            $url = $links[$game->id];
            $key = 'replay.seats.'.hash('sha256', $url);
            $orderedNames = Cache::get($key);
            if ($orderedNames === null) {
                try {
                    $response = Http::connectTimeout(2)->timeout(4)->get($url);
                    $orderedNames = $response->successful() ? $this->parseSeats($response->body()) : [];
                } catch (\Throwable $exception) {
                    report($exception);
                    $orderedNames = [];
                }
                Cache::put($key, $orderedNames, $orderedNames ? 86400 : 300);
            }
            if (count($orderedNames) !== 4 || $this->names($orderedNames) !== $this->names(array_column($game->entries, 'player_name'))) {
                continue;
            }
            foreach (array_values($orderedNames) as $index => $name) {
                foreach ($game->entries as $entry) {
                    if ($this->names([$name]) === $this->names([$entry['player_name']])) {
                        $seats[$game->id][$entry['player_name']] = ['東家', '南家', '西家', '北家'][$index];
                    }
                }
            }
        }

        return $seats;
    }

    /** @return array<int, string> */
    public function parseSeats(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new DOMXPath($document);
            foreach ($xpath->query('//a[@href]') as $anchor) {
                $url = $anchor->getAttribute('href');
                if (! str_starts_with($url, 'https://tenhou.net/5/#json=')) {
                    continue;
                }
                $payload = explode('&', substr($url, strlen('https://tenhou.net/5/#json=')))[0];
                $data = json_decode(rawurldecode($payload), true);
                $names = $data['name'] ?? null;
                if (($data['log'][0][0][0] ?? null) !== 0 || ($data['log'][0][0][1] ?? null) !== 0
                    || ! is_array($names) || count($names) !== 4 || count(array_filter($names, 'is_string')) !== 4) {
                    continue;
                }
                if (count(array_unique($this->names($names))) === 4 && ! in_array('', $this->names($names), true)) {
                    return array_values($names);
                }
            }

            return [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<int, string> */
    public function links(Collection $games): array
    {
        $links = [];
        $eligible = $games->filter(fn (LeagueGame $game) => count(array_filter(array_column($game->entries, 'player_name'))) === 4);
        foreach ($eligible->groupBy(fn (LeagueGame $game) => $game->played_on->format('Y-m')) as $month => $monthlyGames) {
            $season = config('league.season');
            $key = 'replays.'.$season.'.'.$month;
            $rows = Cache::remember($key, 300, function () use ($season, $month) {
                try {
                    $response = Http::connectTimeout(2)->timeout(4)->get('https://m-league.konoui.dev/seasons/'.$season.'/regular/'.$month.'/');
                    if (! $response->successful()) {
                        return [];
                    }

                    return $this->parse($response->body(), $season);
                } catch (\Throwable $exception) {
                    report($exception);

                    return [];
                }
            });
            foreach ($monthlyGames as $game) {
                $names = $this->names(array_column($game->entries, 'player_name'));
                $matches = array_values(array_filter($rows, fn ($row) => $row['day'] === $game->played_on->format('m-d') && $row['round'] === $game->round && $row['players'] === $names));
                if (count($matches) === 1) {
                    $links[$game->id] = $matches[0]['url'];
                }
            }
        }

        return $links;
    }

    /** @return array<int, array{day: string, round: int, players: array, url: string}> */
    public function parse(string $html, string $season): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new DOMXPath($document);
            $rows = [];
            foreach ($xpath->query('//a[@href]') as $anchor) {
                $path = $anchor->getAttribute('href');
                if (! preg_match('~^/seasons/'.preg_quote($season, '~').'/regular/(\d{2}-\d{2})/[AB]-([12])/$~D', $path, $match)) {
                    continue;
                }
                $text = $xpath->evaluate("string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' players ')])", $anchor);
                $names = $this->names(explode(' vs ', $text));
                if (count($names) !== 4 || count(array_unique($names)) !== 4 || in_array('', $names, true)) {
                    continue;
                }
                $rows[] = ['day' => $match[1], 'round' => (int) $match[2], 'players' => $names, 'url' => 'https://m-league.konoui.dev'.$path];
            }

            return $rows;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function names(array $names): array
    {
        $names = array_map(fn ($name) => preg_replace('/\s+/u', '', $name), $names);
        sort($names, SORT_STRING);

        return $names;
    }
}
