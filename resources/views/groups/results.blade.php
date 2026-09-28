@extends('layout')
@section('title', 'ユーザー別対局成績 | Mリーグ選手成績')
@section('content')
<div class="section-head"><div>
    <p class="eyebrow">PLAYER RESULTS</p>
    <h1 class="page-title">ユーザー別対局成績</h1>
    <div class="hero-line" aria-hidden="true"></div>
    <p class="page-intro">登録ユーザーを選ぶと、現在選択している4選手の対局結果を確認できます。</p>
</div></div>
<form method="get" action="{{ route('groups.results') }}" class="game-date-form">
    <label for="results-user">登録ユーザー</label>
    <select id="results-user" name="group" onchange="this.form.submit()">
        <option value="">ユーザーを選択してください</option>
        @foreach($groups as $group)
            <option value="{{ $group->id }}" @selected($selected?->id === $group->id)>{{ $group->name }}</option>
        @endforeach
    </select>
    <button type="submit">表示する</button>
</form>
@if($selected)
    <h2>{{ $selected->name }}さんの選択選手</h2>
    <div class="grid">
        @foreach($selected->players as $player)
            <div class="card"><strong>{{ $player->name }}</strong><br><x-team-badge :team="$player->team_name" /></div>
        @endforeach
    </div>
    <p class="muted">最終取得：{{ $last ? \Carbon\CarbonImmutable::parse($last)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '未取得' }} ／ 獲得ポイント（pt）を表示しています。</p>
    @if($syncError)<p class="alert error">最新情報を取得できていません。前回取得した対局結果を表示しています。</p>@endif
    <section class="card">
        <h2>対局履歴</h2>
        <p class="muted">現在の選択選手について、取得済みの結果を新しい日付順に表示します。</p>
        @if($results->isNotEmpty())
            <div class="game-table-wrap"><table class="game-table">
                <thead><tr><th scope="col">対局日</th><th scope="col">選手</th><th scope="col">着順</th><th scope="col">獲得ポイント</th></tr></thead>
                <tbody>
                @foreach($results as $result)
                    <tr>
                        <td>{{ $result['date'] }}<br><small>第{{ $result['round'] }}回戦</small></td>
                        <td><strong>{{ $result['player_name'] }}</strong><br><x-team-badge :team="$result['team_name']" /></td>
                        <td>{{ $result['rank'] }}位</td>
                        <td class="{{ $result['points'] > 0 ? 'plus' : ($result['points'] < 0 ? 'minus' : '') }}">{{ $result['points'] > 0 ? '+' : '' }}{{ number_format((float) $result['points'], 1) }} pt</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @else
            <p>選択中の選手の確定した対局結果はまだありません。</p>
        @endif
    </section>
@else
    <p class="card">{{ $groups->isEmpty() ? '登録ユーザーがいません。' : 'ユーザーを選択すると4選手の対局成績を表示します。' }}</p>
@endif
<p class="source-note">成績出典：<a href="https://m-league.jp/games/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式対局結果</a></p>
@endsection
