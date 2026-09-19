<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Player extends Model {
    protected $fillable = ['name', 'team_name', 'season_point'];
    protected function casts(): array { return ['season_point' => 'decimal:1']; }
    public function groups(): BelongsToMany { return $this->belongsToMany(Group::class); }
}
