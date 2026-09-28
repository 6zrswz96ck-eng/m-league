<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\LeagueGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class GroupResultsController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['group' => ['nullable', 'integer', 'exists:groups,id']]);
        $groups = Group::orderBy('name')->get(['id', 'name']);
        $selected = $request->filled('group') ? Group::with('players')->findOrFail($request->integer('group')) : null;
        $results = collect();
        if ($selected) {
            $names = $selected->players->pluck('name')->all();
            foreach (LeagueGame::where('status', 'completed')->orderByDesc('played_on')->orderByDesc('round')->orderBy('source_key')->cursor() as $game) {
                foreach ($game->entries as $entry) {
                    if (in_array($entry['player_name'], $names, true)) {
                        $results->push([...$entry, 'date' => $game->played_on->format('Y/m/d'), 'round' => $game->round]);
                    }
                }
            }
        }

        return view('groups.results', [
            'groups' => $groups, 'selected' => $selected, 'results' => $results,
            'last' => Cache::get('games.last_success_at'), 'syncError' => Cache::get('games.sync_error', false),
        ]);
    }
}
