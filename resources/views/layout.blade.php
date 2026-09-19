<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171a1c">
    <title>@yield('title', 'Mリーグ選手成績')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="{{ route('ranking') }}" aria-label="Mリーグ選手成績 トップ">
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="brand-name"><strong>M.LEAGUE</strong><small>PLAYER SCORES</small></span>
        </a>
        <nav class="site-nav" aria-label="メインナビゲーション">
            <a href="{{ route('ranking') }}" @class(['active' => request()->routeIs('ranking')])>ランキング</a>
            <a href="{{ route('players.index') }}" @class(['active' => request()->routeIs('players.index')])>選手紹介</a>
            @if(session('league_admin_authenticated'))
                <a href="{{ route('groups.index') }}" @class(['active' => request()->routeIs('groups.*')])>チーム作成・編集</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="nav-button" type="submit">ログアウト</button></form>
            @else
                <a href="{{ route('login') }}" @class(['active' => request()->routeIs('login')])>管理者ログイン</a>
            @endif
        </nav>
    </div>
</header>
<main class="page-shell">
    @if(session('success'))<p class="alert">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="alert error">{{ session('error') }}</p>@endif
    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@if(request()->routeIs('groups.*'))<script src="{{ asset('js/group-picker.js') }}" defer></script>@endif
</body>
</html>
