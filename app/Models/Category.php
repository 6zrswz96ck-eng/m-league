<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withPivot(['previous_rank', 'last_synced_rank']);
    }
}
