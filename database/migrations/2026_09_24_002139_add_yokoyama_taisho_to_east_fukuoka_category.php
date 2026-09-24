<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('categories')->where('name', '東福岡')->value('id');
        $groupId = DB::table('groups')->where('name', '👑横山大将👑')->value('id');

        if ($categoryId && $groupId) {
            DB::table('category_group')->insertOrIgnore([
                'category_id' => $categoryId,
                'group_id' => $groupId,
            ]);
        }
    }

    public function down(): void
    {
        $categoryId = DB::table('categories')->where('name', '東福岡')->value('id');
        $groupId = DB::table('groups')->where('name', '👑横山大将👑')->value('id');

        if ($categoryId && $groupId) {
            DB::table('category_group')->where([
                'category_id' => $categoryId,
                'group_id' => $groupId,
            ])->delete();
        }
    }
};
