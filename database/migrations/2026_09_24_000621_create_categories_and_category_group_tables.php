<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_group', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('previous_rank')->nullable();
            $table->unsignedInteger('last_synced_rank')->nullable();
            $table->primary(['category_id', 'group_id']);
        });

        $now = now();
        $categories = [
            ['name' => '雀廃', 'groups' => ['益田', '三浦', '本田', '馬場']],
            ['name' => '東福岡', 'groups' => ['益田', 'すぎちゃんず', '古川', '横山']],
        ];

        foreach ($categories as $sortOrder => $category) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => $category['name'],
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $groupIds = DB::table('groups')->whereIn('name', $category['groups'])->pluck('id');
            foreach ($groupIds as $groupId) {
                DB::table('category_group')->insert([
                    'category_id' => $categoryId,
                    'group_id' => $groupId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_group');
        Schema::dropIfExists('categories');
    }
};
