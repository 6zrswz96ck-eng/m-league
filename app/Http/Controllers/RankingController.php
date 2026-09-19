<?php
namespace App\Http\Controllers;
use App\Models\Group;
use App\Services\MLeagueScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class RankingController extends Controller {
    public function index(): View {
        $groups = Group::with('players')->get()->sortByDesc(fn ($g) => $g->players->sum(fn ($p) => (int)round((float)$p->season_point * 10)))->values();
        $last = DB::table('sync_states')->where('id', 1)->value('last_success_at');
        return view('ranking', compact('groups','last'));
    }
    public function update(MLeagueScoreService $service): RedirectResponse {
        try { $count = $service->update(); return back()->with('success', "{$count}選手の成績を更新しました。"); }
        catch (\Throwable $e) { return back()->with('error', '成績を取得できませんでした。前回のデータを保持しています。詳細はログを確認してください。'); }
    }
}
