<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $memberships = [
            '雀廃' => ['益田', '三浦', '本田', '馬場'],
            '東福岡' => ['益田', 'すぎちゃんず', '古川', '横山'],
        ];
        $groups = DB::table('groups')->get(['id', 'name'])->keyBy(
            fn ($group) => preg_replace('/\s+/u', '', trim($group->name))
        );

        foreach ($memberships as $categoryName => $groupNames) {
            $categoryId = DB::table('categories')->where('name', $categoryName)->value('id');
            if (! $categoryId) {
                continue;
            }

            foreach ($groupNames as $groupName) {
                $group = $groups->get($groupName);
                if ($group) {
                    DB::table('category_group')->insertOrIgnore([
                        'category_id' => $categoryId,
                        'group_id' => $group->id,
                    ]);
                }
            }
        }
    }

    public function down(): void {}
};
