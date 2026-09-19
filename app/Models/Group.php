<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Group extends Model {
    protected $fillable = ['name', 'previous_rank', 'last_synced_rank'];
    public function players(): BelongsToMany { return $this->belongsToMany(Player::class); }
}
