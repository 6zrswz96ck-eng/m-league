@extends('layout')
@section('title', '公式成績の手動修正 | Mリーグ選手成績')
@section('content')
<p class="eyebrow">ADMIN / PLAYERS</p>
<h1 class="page-title">公式成績の手動修正</h1>
<p class="page-intro">公式データの取得に失敗した場合に使います。手動修正した値は次の自動更新が成功すると公式の値に戻ります。チームの4人を入れ替える場合は<a href="{{ route('groups.index') }}">チーム作成・編集</a>を開いてください。</p>
@foreach($players as $player)
<section class="card" id="player-{{ $player->id }}">
    <details @if($errors->any() && old('_editing_player') == $player->id) open @endif>
        <summary>{{ $player->name }}　<x-team-badge :team="$player->team_name" /> <span class="muted">編集する</span></summary>
        <form method="post" action="{{ route('players.update', $player) }}">
            @csrf @method('PUT')
            <input type="hidden" name="_editing_player" value="{{ $player->id }}">
            <div class="formrow">
                <label>名前 <input name="name" value="{{ old('_editing_player') == $player->id ? old('name') : $player->name }}" required maxlength="80"></label>
                <label>チーム <input name="team_name" value="{{ old('_editing_player') == $player->id ? old('team_name') : $player->team_name }}" required maxlength="80"></label>
                <label>ポイント <input name="season_point" type="number" step="0.1" value="{{ old('_editing_player') == $player->id ? old('season_point') : $player->season_point }}" required></label>
            </div>
            <p>着順回数（未入力は未取得として表示）</p>
            <div class="formrow">
                @foreach(range(1, 4) as $place)
                    @php($field = 'place_'.$place.'_count')
                    <label>{{ $place }}位 <input name="{{ $field }}" type="number" min="0" max="1000" value="{{ old('_editing_player') == $player->id ? old($field) : $player->{$field} }}"></label>
                @endforeach
            </div>
            <p><button type="submit">変更を保存</button></p>
        </form>
    </details>
</section>
@endforeach
@endsection
