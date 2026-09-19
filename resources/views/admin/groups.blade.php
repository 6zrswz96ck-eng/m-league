@extends('layout')
@section('title', 'チーム作成・編集 | Mリーグ選手成績')
@section('content')
<p class="eyebrow">ADMIN / TEAMS</p>
<h1 class="page-title">チーム作成・編集</h1>
<p class="page-intro">各チームは選手4人で構成します。選び間違えた場合は登録済みチームの「4人を編集」から入れ替えられます。</p>
<section class="card">
    <h2>新しいチームを作成</h2>
    <form method="post" action="{{ route('groups.store') }}" class="group-form" data-group-form>
        @csrf
        <label>チーム名 <input name="name" value="{{ old('name') }}" required maxlength="80"></label>
        @include('admin.partials.group-player-fields', ['selectedIds' => old('players', [])])
        <p><button type="submit" data-submit>4人で作成</button></p>
    </form>
</section>
<h2>登録済みチーム</h2>
@forelse($groups as $group)
    <section class="card group-summary">
        <div><h3>{{ $group->name }}</h3><p>{{ $group->players->pluck('name')->join(' / ') }}</p></div>
        <div class="group-actions"><a class="button" href="{{ route('groups.edit', $group) }}">4人を編集</a><form method="post" action="{{ route('groups.destroy', $group) }}" onsubmit="return confirm('このチームを削除しますか？')">@csrf @method('DELETE')<button class="danger" type="submit">削除</button></form></div>
    </section>
@empty
    <p class="muted">まだチームはありません。</p>
@endforelse
@endsection
