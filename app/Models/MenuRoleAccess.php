<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuRoleAccess extends Model
{
    protected $table = 'menu_role_access';

    protected $fillable = [
        'role_id',
        'menu_path',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
