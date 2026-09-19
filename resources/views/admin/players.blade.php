@extends('layout')
@section('title', '選手編集 | Mリーグ選手成績')
@section('content')
<p class="eyebrow">ADMIN / PLAYERS</p>
<h1 class="page-title">選手の登録・編集</h1>
<p class="page-intro">登録内容を間違えた場合は下の選手を開いて修正できます。ポイントと着順回数は、次の自動更新が成功すると公式の値に戻ります。</p>
<section class="card">
    <h2>新しい選手を登録</h2>
    <form method="post" action="{{ route('players.store') }}">
        @csrf
        <div class="formrow">
            <label>名前 <input name="name" value="{{ old('name') }}" required maxlength="80"></label>
            <label>チーム <input name="team_name" value="{{ old('team_name') }}" required maxlength="80"></label>
            <label>ポイント <input name="season_point" type="number" step="0.1" value="{{ old('season_point', '0') }}" required></label>
            <button type="submit">登録</button>
        </div>
    </form>
</section>
<h2>登録済み選手</h2>
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
