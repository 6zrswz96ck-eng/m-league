<?php
namespace App\Http\Controllers;
use App\Http\Requests\GroupRequest;
use App\Models\Group;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class GroupController extends Controller {
    public function index(): View { return view('admin.groups', ['groups' => Group::with('players')->get(), 'players' => Player::orderBy('team_name')->orderBy('name')->get()]); }
    public function edit(Group $group): View { return view('admin.group-edit', ['group' => $group->load('players'), 'players' => Player::orderBy('team_name')->orderBy('name')->get()]); }
    public function store(GroupRequest $request): RedirectResponse {
        DB::transaction(function () use ($request) { $group = Group::create($request->safe()->only('name')); $group->players()->sync($request->validated('players')); });
        return redirect()->route('groups.index')->with('success','チームを作成しました。');
    }
    public function update(GroupRequest $request, Group $group): RedirectResponse {
        DB::transaction(function () use ($request, $group) { $group->update([...$request->safe()->only('name'), 'previous_rank' => null, 'last_synced_rank' => null]); $group->players()->sync($request->validated('players')); });
        return redirect()->route('groups.index')->with('success','チームの4人を更新しました。');
    }
    public function destroy(Group $group): RedirectResponse { $group->delete(); return back()->with('success','グループを削除しました。'); }
}
