@extends('layout')
@section('title', $teamName.' | チームポイントランキング')
@section('content')
<p><a href="{{ route('teams.ranking') }}">← チームポイントランキングに戻る</a></p>
<div class="section-head">
    <div>
        <p class="eyebrow">TEAM MEMBERS / 2026–27</p>
        <h1 class="page-title">{{ $teamName }}</h1>
        <div class="hero-line" aria-hidden="true"></div>
        <p class="page-intro">所属選手と今シーズンの個人ポイントです。</p>
    </div>
    @if($team)
        <div class="team-total"><small>現在のチームポイント</small><strong class="point {{ $team['points'] > 0 ? 'plus' : ($team['points'] < 0 ? 'minus' : '') }}">{{ $team['points'] > 0 ? '+' : '' }}{{ number_format($team['points'] / 10, 1) }} pt</strong><span>{{ $team['rank'] }}位</span></div>
    @endif
</div>
<div class="profile-grid">
    @foreach($players as $player)
        @php($points = (int) round((float) $player->season_point * 10))
        <article class="profile-card" style="--team-color: {{ \App\Support\TeamTheme::color($teamName) }}">
            <span class="ordinal">PLAYER {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <h2><a class="player-link" href="{{ route('players.show', ['player' => $player, 'return' => '/teams/details?team='.urlencode($teamName)]) }}">{{ $player->name }} ↗</a></h2>
            <x-team-badge :team="$teamName" />
            <div class="profile-score"><small>今季個人ポイント</small><strong class="{{ $points > 0 ? 'plus' : ($points < 0 ? 'minus' : '') }}">{{ $points > 0 ? '+' : '' }}{{ number_format($points / 10, 1) }} pt</strong></div>
        </article>
    @endforeach
</div>
<p class="source-note">最終取得：{{ $last ? \Carbon\CarbonImmutable::parse($last)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '未取得' }} ／ 成績出典：<a href="https://m-league.jp/stats/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式成績表</a></p>
@endsection
