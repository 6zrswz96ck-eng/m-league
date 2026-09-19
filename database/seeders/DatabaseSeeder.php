<?php

namespace Database\Seeders;

use App\Models\Player;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            'EARTH JETS' => ['石井 一馬','三浦 智博','逢川 恵夢','HIRO 柴田'],
            'KADOKAWAサクラナイツ' => ['岡田 紗佳','堀 慎吾','阿久津 翔太','尻無濱 航'],
            'U-NEXT Pirates' => ['瑞原 明奈','鈴木 優','仲林 圭','朝倉 康心'],
            '渋谷ABEMAS' => ['多井 隆晴','白鳥 翔','松本 吉弘','日向 藍子'],
            '赤坂ドリブンズ' => ['園田 賢','鈴木 たろう','浅見 真紀','渡辺 太'],
            'セガサミーフェニックス' => ['茅森 早香','醍醐 大','竹内 元太','佐野 ひなこ'],
            'BEAST X' => ['鈴木 大介','中田 花奈','下石 戟','東城 りお'],
            'TEAM RAIDEN / 雷電' => ['萩原 聖人','瀬戸熊 直樹','黒沢 咲','本田 朋広'],
            'KONAMI 麻雀格闘倶楽部' => ['佐々木 寿人','高宮 まり','伊達 朱里紗','滝沢 和典'],
            'EX風林火山' => ['二階堂 亜樹','勝又 健志','永井 孝典','内川 幸太郎'],
        ];
        foreach ($teams as $team => $names) foreach ($names as $name) Player::firstOrCreate(['name' => $name], ['team_name' => $team, 'season_point' => 0]);
    }
}
