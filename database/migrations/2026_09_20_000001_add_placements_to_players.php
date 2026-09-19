<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            foreach (range(1, 4) as $place) {
                $table->unsignedInteger("place_{$place}_count")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            foreach (range(1, 4) as $place) {
                $table->dropColumn("place_{$place}_count");
            }
        });
    }
};
