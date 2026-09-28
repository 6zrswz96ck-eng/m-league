<?php

namespace App\Http\Controllers;

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
}
