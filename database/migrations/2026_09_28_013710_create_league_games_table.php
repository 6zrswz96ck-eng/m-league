<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('league_games', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->date('played_on')->index();
            $table->unsignedTinyInteger('round');
            $table->json('teams');
            $table->json('entries');
            $table->string('status');
            $table->timestamp('last_checked_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_games');
    }
};
