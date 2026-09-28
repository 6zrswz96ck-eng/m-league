<?php

use App\Services\MLeagueGameService;
use App\Services\MLeagueScoreService;
use App\Services\MLeagueTeamService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('mleague:update', function (MLeagueScoreService $service) {
    try {
        $this->info($service->update().'選手を更新しました。');

        return 0;
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return 1;
    }
})->purpose('Mリーグ公式サイトから今季の個人成績を更新');
Schedule::command('mleague:update')->hourly()->withoutOverlapping();

Artisan::command('mleague:teams', function (MLeagueTeamService $service) {
    try {
        $this->info($service->update().'チームの成績を更新しました。');

        return 0;
    } catch (Throwable $exception) {
        report($exception);
        $this->error('チーム成績を取得できませんでした。保存済みの成績を保持します。');

        return 1;
    }
})->purpose('公式チームランキングを更新');
Schedule::command('mleague:teams')->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();

Artisan::command('mleague:games {--history : 今季の過去の月も再取得}', function (MLeagueGameService $service) {
    try {
        $this->info($service->update((bool) $this->option('history')).'件の対局情報を確認しました。');

        return 0;
    } catch (Throwable $exception) {
        report($exception);
        $this->error('対局情報の取得に失敗しました。保存済みの情報を保持しています。');

        return 1;
    }
})->purpose('公式の対局日程・結果・出場予定を更新');

Schedule::command('mleague:games')->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();
Schedule::command('mleague:games --history')->dailyAt('04:10')->timezone('Asia/Tokyo')->withoutOverlapping(10)->runInBackground();
