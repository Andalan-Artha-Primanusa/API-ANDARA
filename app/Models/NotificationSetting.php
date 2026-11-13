<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'label',
        'description',
        'in_app',
        'email',
        'push',
    ];

    protected $casts = [
        'in_app' => 'boolean',
        'email' => 'boolean',
        'push' => 'boolean',
    ];
}
