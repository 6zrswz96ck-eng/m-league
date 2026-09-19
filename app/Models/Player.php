<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Player extends Model {
    protected $fillable = ['name', 'team_name', 'season_point', 'place_1_count', 'place_2_count', 'place_3_count', 'place_4_count'];
    protected function casts(): array { return ['season_point' => 'decimal:1', 'place_1_count' => 'integer', 'place_2_count' => 'integer', 'place_3_count' => 'integer', 'place_4_count' => 'integer']; }
    public function groups(): BelongsToMany { return $this->belongsToMany(Group::class); }
}
