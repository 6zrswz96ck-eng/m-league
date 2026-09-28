<?php

namespace App\Http\Controllers;

use App\Models\LeagueGame;
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
        $date = $request->input('date') ?: $today;
        $dates = LeagueGame::query()->distinct()->orderByDesc('played_on')->pluck('played_on')
            ->map(fn ($day) => CarbonImmutable::parse($day)->toDateString())
            ->push($today)->push($date)->unique()->sortDesc()->values();
        $games = LeagueGame::whereDate('played_on', $date)->orderBy('round')->orderBy('source_key')->get();

        return view('games.index', [
            'dates' => $dates, 'date' => $date, 'today' => $today, 'games' => $games,
            'last' => Cache::get('games.last_success_at'), 'syncError' => Cache::get('games.sync_error', false),
        ]);
    }
}
