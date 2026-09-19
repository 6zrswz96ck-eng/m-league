@extends('layout')
@section('content')
<section class="card" style="max-width:420px;margin:32px auto"><h1>管理者ログイン</h1><form method="post" action="{{ route('login.submit') }}">@csrf
<label>ユーザー名<br><input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus style="width:100%;box-sizing:border-box"></label>
<label>パスワード<br><input name="password" type="password" autocomplete="current-password" required style="width:100%;box-sizing:border-box"></label>
<p><button type="submit">ログイン</button></p></form></section>
@endsection
