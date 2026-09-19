<?php
namespace App\Http\Controllers;
use App\Http\Requests\PlayerRequest;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class PlayerController extends Controller {
    public function index(): View { return view('players.index', ['teams' => Player::orderBy('team_name')->orderBy('name')->get()->groupBy('team_name')]); }
    public function manage(): View { return view('admin.players', ['players' => Player::orderBy('team_name')->orderBy('name')->get()]); }
    public function store(PlayerRequest $request): RedirectResponse { Player::create($request->validated()); return back()->with('success','選手を登録しました。'); }
    public function update(PlayerRequest $request, Player $player): RedirectResponse { $player->update($request->validated()); return back()->with('success','選手を更新しました。'); }
}
