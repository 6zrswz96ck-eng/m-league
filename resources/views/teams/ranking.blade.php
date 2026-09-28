@extends('layout')
@section('title', 'チームポイントランキング | Mリーグ選手成績')
@section('content')
<div class="section-head"><div>
    <p class="eyebrow">TEAM RANKING / REGULAR SEASON</p>
    <h1 class="page-title">チームポイントランキング</h1>
    <div class="hero-line" aria-hidden="true"></div>
    <p class="page-intro">Mリーグ公式のレギュラーシーズン順位・ポイントです。</p>
</div></div>
<p class="muted">最終取得：{{ $last ? \Carbon\CarbonImmutable::parse($last)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '未取得' }}</p>
@if($syncError)<p class="alert error">最新情報を取得できていません。前回取得した情報を表示しています。</p>@endif
<div class="individual-ranking">
@forelse($teams as $team)
    @if(!$loop->first && $teams[$loop->index - 1]['rank'] <= 6 && $team['rank'] > 6)
        <div class="team-cutoff" role="separator" aria-label="6位ボーダー"><span>6位ボーダー</span></div>
    @endif
    <article class="individual-row" style="--team-color: {{ \App\Support\TeamTheme::color($team['name']) }}">
        <span class="individual-rank {{ $team['points'] === $minimum ? 'minus' : 'plus' }}">{{ $team['rank'] }}位</span>
        <div class="individual-player"><x-team-badge :team="$team['name']" /><small>試合数 {{ $team['games'] }} ・ 1つ上との差 {{ $loop->first ? '—' : number_format(($teams[$loop->index - 1]['points'] - $team['points']) / 10, 1).' pt' }}</small>@if($team['rank'] === 6)<span class="team-cutoff-label">ボーダー順位</span>@endif</div>
        <strong class="point {{ $team['points'] > 0 ? 'plus' : ($team['points'] < 0 ? 'minus' : '') }}">{{ $team['points'] > 0 ? '+' : '' }}{{ number_format($team['points'] / 10, 1) }} pt</strong>
    </article>
@empty
    <p class="card">チーム成績を取得準備中です。しばらくしてから再読み込みしてください。</p>
@endforelse
</div>
<p class="source-note">成績出典: <a href="https://m-league.jp/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式チームランキング</a></p>
@endsection
