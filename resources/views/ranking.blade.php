@extends('layout')
@section('title', 'グループランキング | Mリーグ選手成績')
@section('content')
<div class="section-head">
    <div>
        <p class="eyebrow">2026–27 REGULAR SEASON</p>
        <h1 class="page-title">グループランキング</h1>
        <div class="hero-line" aria-hidden="true"></div>
        <p class="page-intro">表示するカテゴリーを選択できます。順位と最下位との差は、表示中のグループ内で計算します。</p>
    </div>
    <div class="update-box">
        <form method="post" action="{{ route('update') }}">@csrf<button type="submit">最新成績に更新 <span aria-hidden="true">↗</span></button></form>
        <span class="muted">最終更新 {{ $last ? \Carbon\Carbon::parse($last)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '未更新' }}</span>
    </div>
</div>

<nav class="category-switcher" aria-label="ランキングカテゴリー">
    <a href="{{ route('ranking') }}" @class(['active' => $activeCategory === null])>すべて</a>
    @foreach($categories as $category)
        <a href="{{ route('ranking', ['category' => $category->id]) }}" @class(['active' => $activeCategory?->is($category)])>{{ $category->name }}</a>
    @endforeach
</nav>

<div class="category-heading">
    <p class="eyebrow">{{ $activeCategory ? 'CATEGORY' : 'ALL GROUPS' }}</p>
    <h2>{{ $activeCategory?->name ?? 'すべて' }}</h2>
</div>
@php($previousTotal = null)
@php($rank = 0)
@php($highestTotal = $groups->first()?->players->sum(fn ($player) => (int) round((float) $player->season_point * 10)))
@php($lowestTotal = $groups->last()?->players->sum(fn ($player) => (int) round((float) $player->season_point * 10)))
<div class="ranking-list">
@forelse($groups as $group)
    @php($totalTenths = $group->players->sum(fn ($player) => (int) round((float) $player->season_point * 10)))
    @php($total = $totalTenths / 10)
    @php($isLast = $highestTotal !== $lowestTotal && $totalTenths === $lowestTotal)
    @php($rank = $previousTotal === $total ? $rank : $loop->iteration)
    @php($previousTotal = $total)
    @php($storedPreviousRank = $activeCategory ? $group->pivot->previous_rank : $group->previous_rank)
    @php($rankChange = $storedPreviousRank === null ? null : $storedPreviousRank - $rank)
    <section @class(['card', 'rank-card', 'is-last' => $isLast])>
        <div class="rank-header">
            <div class="rank-title">
                <span class="rank-number">{{ $rank }}位</span>
                <h3>{{ $group->name }}</h3>
                @if($isLast)<span class="last-label">最下位</span>@endif
                @if($rankChange !== null)<span @class(['rank-change', 'rank-up' => $rankChange > 0, 'rank-down' => $rankChange < 0])>{{ $rankChange > 0 ? '↑'.$rankChange : ($rankChange < 0 ? '↓'.abs($rankChange) : '→ 変動なし') }}</span>@endif
            </div>
            <div class="rank-score"><strong class="point {{ $total > 0 ? 'plus' : ($total < 0 ? 'minus' : '') }}">{{ $total > 0 ? '+' : '' }}{{ number_format($total, 1) }} pt</strong><span class="rank-gap">最下位との差 {{ number_format(($totalTenths - $lowestTotal) / 10, 1) }} pt</span></div>
        </div>
        <div class="rank-players">
        @foreach($group->players as $player)
            <div class="player">
                <div><a class="player-name player-link" href="{{ route('players.show', $player) }}">{{ $player->name }} <span aria-hidden="true">↗</span></a><x-team-badge :team="$player->team_name" /></div>
                <strong class="point {{ $player->season_point > 0 ? 'plus' : ($player->season_point < 0 ? 'minus' : '') }}">{{ $player->season_point > 0 ? '+' : '' }}{{ number_format((float) $player->season_point, 1) }} pt</strong>
            </div>
        @endforeach
        </div>
        @if(session('league_admin_authenticated'))<div class="rank-actions"><a href="{{ route('groups.edit', $group) }}">このグループの4人を編集</a></div>@endif
    </section>
@empty
    <p class="card">表示できるグループがありません。</p>
@endforelse
</div>

<p class="source-note">成績出典: <a href="https://m-league.jp/stats/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式成績表</a></p>
@endsection
