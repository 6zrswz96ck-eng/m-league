<?php

namespace App\Support;

class TeamTheme
{
    private const TEAMS = [
        'EARTH JETS' => ['#176b4a', 'jets'],
        'KADOKAWAサクラナイツ' => ['#c52e8c', 'sakuraknights'],
        'U-NEXT Pirates' => ['#16578d', 'pirates'],
        '渋谷ABEMAS' => ['#a96e13', 'abemas'],
        '赤坂ドリブンズ' => ['#4f7d20', 'drivens'],
        'セガサミーフェニックス' => ['#b8580b', 'phoenix'],
        'BEAST X' => ['#326b4c', 'beast'],
        'TEAM RAIDEN / 雷電' => ['#d6ad00', 'raiden'],
        'KONAMI 麻雀格闘倶楽部' => ['#7a1025', 'fightclub'],
        'EX風林火山' => ['#a92732', 'furinkazan'],
    ];

    public static function color(string $name): string
    {
        return self::TEAMS[$name][0] ?? '#46546a';
    }

    public static function url(string $name): ?string
    {
        return isset(self::TEAMS[$name]) ? 'https://m-league.jp/teams/'.self::TEAMS[$name][1].'/' : null;
    }

    public static function textColor(string $name): string
    {
        return match ($name) {
            'EX風林火山' => '#202124',
            'TEAM RAIDEN / 雷電' => '#665100',
            default => self::color($name),
        };
    }
}
