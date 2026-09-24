<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\MLeagueScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        $categories = Category::with('groups.players')->orderBy('sort_order')->get()->map(function (Category $category): Category {
            $category->setRelation('groups', $category->groups->sortByDesc(
                fn ($group) => $group->players->sum(fn ($player) => (int) round((float) $player->season_point * 10))
            )->values());

            return $category;
        });
        $last = DB::table('sync_states')->where('id', 1)->value('last_success_at');

        return view('ranking', compact('categories', 'last'));
    }

    public function update(MLeagueScoreService $service): RedirectResponse
    {
        try {
            $count = $service->update();

            return back()->with('success', "{$count}選手の成績を更新しました。");
        } catch (\Throwable $e) {
            return back()->with('error', '成績を取得できませんでした。前回のデータを保持しています。詳細はログを確認してください。');
        }
    }
}
