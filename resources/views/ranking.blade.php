@extends('layout')
@section('title', 'ランキング | Mリーグ選手成績')
@section('content')
<div class="section-head">
    <div>
        <p class="eyebrow">2026–27 REGULAR SEASON</p>
        <h1 class="page-title">グループランキング</h1>
        <div class="hero-line" aria-hidden="true"></div>
        <p class="page-intro">選んだ4人の今季ポイントを合計。最下位を避けるための順位と内訳を確認できます。</p>
    </div>
    <div class="update-box">
        <form method="post" action="{{ route('update') }}">@csrf<button type="submit">最新成績に更新 <span aria-hidden="true">↗</span></button></form>
        <span class="muted">最終更新 {{ $last ? \Carbon\Carbon::parse($last)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '未更新' }}</span>
    </div>
</div>
@php($previousTotal = null)
@php($rank = 0)
@php($highestTotal = $groups->first()?->players->sum(fn ($p) => (int) round((float) $p->season_point * 10)))
@php($lowestTotal = $groups->last()?->players->sum(fn ($p) => (int) round((float) $p->season_point * 10)))
<div class="ranking-list">
@forelse($groups as $group)
    @php($totalTenths = $group->players->sum(fn ($p) => (int) round((float) $p->season_point * 10)))
    @php($total = $totalTenths / 10)
    @php($isLast = $highestTotal !== $lowestTotal && $totalTenths === $lowestTotal)
    @php($rank = $previousTotal === $total ? $rank : $loop->iteration)
    @php($previousTotal = $total)
    <section @class(['card', 'rank-card', 'is-last' => $isLast])>
        <div class="rank-header">
            <div class="rank-title"><span class="rank-number">{{ $rank }}位</span><h2>{{ $group->name }}</h2>@if($isLast)<span class="last-label">最下位</span>@endif</div>
            <strong class="point {{ $total > 0 ? 'plus' : ($total < 0 ? 'minus' : '') }}">{{ $total > 0 ? '+' : '' }}{{ number_format($total, 1) }} pt</strong>
        </div>
        <div class="rank-players">
        @foreach($group->players as $player)
            <div class="player">
                <div><div class="player-name">{{ $player->name }}</div><x-team-badge :team="$player->team_name" /></div>
                <strong class="point {{ $player->season_point > 0 ? 'plus' : ($player->season_point < 0 ? 'minus' : '') }}">{{ $player->season_point > 0 ? '+' : '' }}{{ number_format((float) $player->season_point, 1) }} pt</strong>
            </div>
        @endforeach
        </div>
    </section>
@empty
    <p class="card">まだグループがありません。@if(session('league_admin_authenticated'))<a href="{{ route('groups.index') }}">グループを作成</a>してください。@else 管理者が登録するとここにランキングが表示されます。@endif</p>
@endforelse
</div>
<p class="source-note">成績出典: <a href="https://m-league.jp/stats/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式成績表</a></p>
@endsection
