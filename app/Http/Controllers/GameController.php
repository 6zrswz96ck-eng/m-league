<?php

namespace App\Http\Controllers;

use App\Models\LeagueGame;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $today = CarbonImmutable::now('Asia/Tokyo')->toDateString();
        $dates = LeagueGame::query()->distinct()->orderByDesc('played_on')->pluck('played_on')
            ->map(fn ($day) => CarbonImmutable::parse($day)->toDateString())
            ->unique()->values();
        $defaultDate = $dates->first(fn (string $day) => $day <= $today) ?? $dates->last() ?? $today;
        $date = $request->input('date') ?: $defaultDate;
        $games = LeagueGame::whereDate('played_on', $date)->orderBy('round')->orderBy('source_key')->get();
        $playerNames = $games->flatMap(fn (LeagueGame $game) => array_column($game->entries, 'player_name'))->filter()->unique();
        $selectedBy = Player::with(['groups' => fn ($query) => $query->orderBy('name')])
            ->whereIn('name', $playerNames)->get()->mapWithKeys(fn (Player $player) => [$player->name => $player->groups]);

        return view('games.index', [
            'dates' => $dates, 'date' => $date, 'today' => $today, 'games' => $games,
            'selectedBy' => $selectedBy,
            'last' => Cache::get('games.last_success_at'), 'syncError' => Cache::get('games.sync_error', false),
        ]);
    }
}
