@extends('layout')
@section('title', '対局日程・結果 | Mリーグ選手成績')
@section('content')
<div class="section-head">
    <div>
        <p class="eyebrow">SCHEDULE & RESULTS / 2026–27</p>
        <h1 class="page-title">対局日程・結果</h1>
        <div class="hero-line" aria-hidden="true"></div>
        <p class="page-intro">日付を選んで出場選手と対局結果を確認できます。</p>
    </div>
    <a class="button" href="https://m-league.konoui.dev/" target="_blank" rel="noopener noreferrer">牌譜一覧を開く <span aria-hidden="true">↗</span></a>
</div>
<p class="muted">牌譜は別タブで開きます。リンク先でシーズン・日付・対局を選んでください。</p>
<form method="get" action="{{ route('games.index') }}" class="game-date-form">
    <label for="game-date">対局日</label>
    <select id="game-date" name="date" onchange="this.form.submit()">
        @if(!$dates->contains($date))<option value="" selected disabled>{{ $dates->isEmpty() ? '公開済みの対局日程はありません' : '対局日を選択してください' }}</option>@endif
        @foreach($dates as $option)
            <option value="{{ $option }}" @selected($date === $option)>{{ \Carbon\CarbonImmutable::parse($option)->format('Y/m/d') }}（{{ ['日', '月', '火', '水', '木', '金', '土'][\Carbon\CarbonImmutable::parse($option)->dayOfWeek] }}）{{ $option === $today ? ' 今日' : '' }}</option>
        @endforeach
    </select>
    <button type="submit">表示する</button>
</form>
<p class="muted">最終確認：{{ $last ? \Carbon\CarbonImmutable::parse($last)->timezone('Asia/Tokyo')->format('Y/m/d H:i') : '未取得' }}　／ 点数は獲得ポイント（pt）です。</p>
@if($syncError)<p class="alert error">最新の情報を取得できていません。前回取得した情報を表示しています。</p>@endif
@forelse($games as $game)
    <section class="card game-card">
        <div class="top">
            <h2>第{{ $game->round }}回戦</h2>
            <span class="game-status">{{ ['completed' => '結果確定', 'announced' => '出場予定・結果待ち', 'scheduled' => '出場選手の発表待ち'][$game->status] }}</span>
        </div>
        <div class="game-table-wrap">
            <table class="game-table">
                <thead><tr><th scope="col">着順</th><th scope="col">選手・所属チーム</th><th scope="col">獲得ポイント</th></tr></thead>
                <tbody>
                @foreach($game->entries as $entry)
                    <tr>
                        <td>{{ $entry['rank'] !== null ? $entry['rank'].'位' : '—' }}</td>
                        <td>
                            <strong>{{ $entry['player_name'] ?? '出場選手未発表' }}</strong><br><x-team-badge :team="$entry['team_name']" />
                            @if($entry['player_name'] !== null && $selectedBy->get($entry['player_name'])?->isNotEmpty())
                                <div class="game-selections"><span class="game-selections-label">この選手を選択中</span>
                                    @foreach($selectedBy->get($entry['player_name']) as $group)
                                        <span class="game-selection-name">{{ $group->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="{{ $entry['points'] !== null && $entry['points'] > 0 ? 'plus' : ($entry['points'] !== null && $entry['points'] < 0 ? 'minus' : '') }}">{{ $entry['points'] === null ? '—' : ($entry['points'] > 0 ? '+' : '').number_format((float) $entry['points'], 1).' pt' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@empty
    <p class="card">{{ $last ? 'この日の対局情報はありません。日程が未掲載の場合は、公開後に表示されます。' : '対局情報を取得準備中です。しばらくしてから再読み込みしてください。' }}</p>
@endforelse
<p class="source-note">出典：<a href="https://m-league.jp/games/" target="_blank" rel="noopener noreferrer">M.LEAGUE 公式日程・結果</a>、<a href="https://m-league.jp/" target="_blank" rel="noopener noreferrer">公式出場予定</a></p>
@if($date === $today)<script>window.setInterval(() => { if (!document.hidden) window.location.reload(); }, 60000);</script>@endif
@endsection
