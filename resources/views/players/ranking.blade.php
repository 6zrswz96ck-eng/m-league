@extends('layout')
@section('title', '選手ポイントランキング | Mリーグ選手成績')
@section('content')
<div class="section-head">
    <div>
        <p class="eyebrow">2026–27 INDIVIDUAL RANKING</p>
        <h1 class="page-title">選手ポイントランキング</h1>
        <div class="hero-line" aria-hidden="true"></div>
        <p class="page-intro">今シーズンの個人ポイント順です。同点の選手は同順位で表示します。選手名を押すと着順回数を確認できます。</p>
    </div>
</div>
@php($previousPoints = null)
@php($rank = 0)
<div class="individual-ranking">
    @forelse($players as $player)
        @php($points = (int) round((float) $player->season_point * 10))
        @php($rank = $points === $previousPoints ? $rank : $loop->iteration)
        @php($previousPoints = $points)
        <article class="individual-row" style="--team-color: {{ \App\Support\TeamTheme::color($player->team_name) }}">
            <span class="individual-rank">{{ $rank }}位</span>
            <div class="individual-player"><a class="player-link" href="{{ route('players.show', $player) }}">{{ $player->name }} <span aria-hidden="true">↗</span></a><x-team-badge :team="$player->team_name" /></div>
            <strong class="point {{ $points > 0 ? 'plus' : ($points < 0 ? 'minus' : '') }}">{{ $points > 0 ? '+' : '' }}{{ number_format($points / 10, 1) }} pt</strong>
        </article>
    @empty
        <p class="card">選手データがありません。</p>
    @endforelse
</div>
<p class="source-note">成績出典: <a href="https://m-league.jp/stats/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式成績表</a></p>
@endsection
