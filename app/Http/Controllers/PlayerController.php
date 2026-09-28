<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerRequest;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function index(): View
    {
        return view('players.index', ['teams' => Player::orderBy('team_name')->orderBy('name')->get()->groupBy('team_name')]);
    }

    public function ranking(): View
    {
        return view('players.ranking', ['players' => Player::orderByDesc('season_point')->orderBy('name')->get()]);
    }

    public function show(Request $request, Player $player): View
    {
        $returnTo = $request->query('return');
        if (! is_string($returnTo) || ! preg_match('~^(?:/(?:\?category=\d+)?|/players(?:/ranking)?)$~D', $returnTo)) {
            $returnTo = '/';
        }
        $returnLabel = match ($returnTo) {
            '/players/ranking' => '個人ポイントランキングに戻る',
            '/players' => '選手紹介に戻る',
            default => 'グループランキングに戻る',
        };

        return view('players.show', compact('player', 'returnTo', 'returnLabel'));
    }

    public function manage(): View
    {
        return view('admin.players', ['players' => Player::orderBy('team_name')->orderBy('name')->get()]);
    }

    public function update(PlayerRequest $request, Player $player): RedirectResponse
    {
        $player->update($request->validated());

        return back()->with('success', '選手を更新しました。');
    }
}
