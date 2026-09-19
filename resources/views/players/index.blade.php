@extends('layout')
@section('title', '選手紹介 | Mリーグ選手成績')
@section('content')
<div class="section-head">
    <div>
        <p class="eyebrow">2026–27 PLAYERS</p>
        <h1 class="page-title">選手紹介</h1>
        <div class="hero-line" aria-hidden="true"></div>
        <p class="page-intro">今シーズンの選手をチーム別に紹介します。ポイントは最新の取得結果です。</p>
    </div>
</div>
@foreach($teams as $teamName => $players)
    @php($color = \App\Support\TeamTheme::color($teamName))
    @php($officialUrl = \App\Support\TeamTheme::url($teamName))
    <section class="team-section" style="--team-color: {{ $color }}">
        <div class="team-heading">
            <h2>{{ $teamName }}</h2>
            @if($officialUrl)<a href="{{ $officialUrl }}" style="color: {{ \App\Support\TeamTheme::textColor($teamName) }}" target="_blank" rel="noopener noreferrer">公式の選手紹介を見る ↗</a>@endif
        </div>
        <div class="profile-grid">
            @foreach($players as $player)
                <article class="profile-card">
                    <span class="ordinal">PLAYER {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $player->name }}</h3>
                    <x-team-badge :team="$player->team_name" />
                    <div class="profile-score"><small>今季個人スコア</small><strong class="{{ $player->season_point > 0 ? 'plus' : ($player->season_point < 0 ? 'minus' : '') }}">{{ $player->season_point > 0 ? '+' : '' }}{{ number_format((float) $player->season_point, 1) }} pt</strong></div>
                </article>
            @endforeach
        </div>
    </section>
@endforeach
<p class="source-note">選手・成績情報: <a href="https://m-league.jp/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式サイト</a></p>
@endsection
