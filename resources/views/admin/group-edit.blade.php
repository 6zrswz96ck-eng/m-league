@extends('layout')
@section('title', $group->name.'の編集 | Mリーグ選手成績')
@section('content')
<p><a href="{{ route('groups.index') }}">← チーム一覧に戻る</a></p>
<p class="eyebrow">ADMIN / EDIT TEAM</p>
<h1 class="page-title">{{ $group->name }}の4人を編集</h1>
<p class="page-intro">選び直して保存すると、ランキングのメンバーも入れ替わります。</p>
<section class="card">
    <form method="post" action="{{ route('groups.update', $group) }}" class="group-form" data-group-form>
        @csrf @method('PUT')
        <label>チーム名 <input name="name" value="{{ old('name', $group->name) }}" required maxlength="80"></label>
        @include('admin.partials.group-player-fields', ['selectedIds' => old('players', $group->players->pluck('id')->all())])
        <p><button type="submit" data-submit>変更を保存</button></p>
    </form>
</section>
@endsection
