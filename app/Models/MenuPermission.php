<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuPermission extends Model
{
    protected $fillable = ['menu_key', 'role_id'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
