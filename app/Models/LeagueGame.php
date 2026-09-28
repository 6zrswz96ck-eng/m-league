<?php

namespace App\Models;

use Database\Factories\LeagueGameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeagueGame extends Model
{
    /** @use HasFactory<LeagueGameFactory> */
    use HasFactory;

    protected $fillable = ['source_key', 'played_on', 'round', 'teams', 'entries', 'status', 'last_checked_at'];

    protected function casts(): array
    {
        return ['played_on' => 'date', 'teams' => 'array', 'entries' => 'array', 'last_checked_at' => 'datetime'];
    }
}
