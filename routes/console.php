<?php

use App\Services\MLeagueScoreService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('mleague:update', function (MLeagueScoreService $service) {
    try { $this->info($service->update().'選手を更新しました。'); return 0; }
    catch (\Throwable $e) { $this->error($e->getMessage()); return 1; }
})->purpose('Mリーグ公式サイトから今季の個人成績を更新');
Schedule::command('mleague:update')->hourly()->withoutOverlapping();
