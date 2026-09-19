@extends('layout')
@section('title', $player->name.' | Mリーグ選手成績')
@section('content')
<p><a href="{{ route('ranking') }}">← ランキングに戻る</a></p>
<section class="card player-detail" style="--team-color: {{ \App\Support\TeamTheme::color($player->team_name) }}">
    <p class="eyebrow">PLAYER STATS / 2026–27</p>
    <h1 class="page-title">{{ $player->name }}</h1>
    <x-team-badge :team="$player->team_name" />
    <div class="profile-score"><small>今季個人スコア</small><strong class="{{ $player->season_point > 0 ? 'plus' : ($player->season_point < 0 ? 'minus' : '') }}">{{ $player->season_point > 0 ? '+' : '' }}{{ number_format((float) $player->season_point, 1) }} pt</strong></div>
    <h2>着順回数</h2>
    @if($player->place_1_count === null)
        <p class="muted">着順データは次の成績更新後に表示されます。</p>
    @else
        <div class="placements">
            @foreach(range(1, 4) as $place)
                <div><span>{{ $place }}位</span><strong>{{ $player->{'place_'.$place.'_count'} }}回</strong></div>
            @endforeach
        </div>
    @endif
</section>
<p class="source-note">成績出典: <a href="https://m-league.jp/stats/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式成績表</a></p>
@endsection
