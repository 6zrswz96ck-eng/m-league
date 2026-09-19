<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('team_name');
            $table->decimal('season_point', 8, 1)->default(0);
            $table->timestamps();
        });
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('group_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->unique(['group_id', 'player_id']);
        });
        Schema::create('sync_states', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('sync_states');
        Schema::dropIfExists('group_player');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('players');
    }
};
