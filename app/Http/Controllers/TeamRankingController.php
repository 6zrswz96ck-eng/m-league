<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TeamRankingController extends Controller
{
    public function index(): View
    {
        $snapshot = Cache::get('teams.ranking', ['teams' => [], 'updated_at' => null]);

        return view('teams.ranking', [
            'teams' => $snapshot['teams'], 'last' => $snapshot['updated_at'],
            'minimum' => collect($snapshot['teams'])->min('points'),
            'syncError' => Cache::get('teams.sync_error', false),
        ]);
    }

    public function show(Request $request): View
    {
        $request->validate(['team' => ['required', 'string', 'max:100']]);
        $teamName = $request->string('team')->toString();
        $players = Player::where('team_name', $teamName)->orderByDesc('season_point')->orderBy('name')->get();
        abort_if($players->isEmpty(), 404);

        $snapshot = Cache::get('teams.ranking', ['teams' => [], 'updated_at' => null]);
        $team = collect($snapshot['teams'])->firstWhere('name', $teamName);

        return view('teams.show', [
            'teamName' => $teamName,
            'players' => $players,
            'team' => $team,
            'last' => $snapshot['updated_at'],
        ]);
    }
}
