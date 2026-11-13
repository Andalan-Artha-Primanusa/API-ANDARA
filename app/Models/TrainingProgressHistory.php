<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingProgressHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_enrollment_id',
        'user_id',
        'old_score',
        'new_score',
        'old_status',
        'new_status',
        'notes',
    ];

    protected $casts = [
        'old_score' => 'decimal:2',
        'new_score' => 'decimal:2',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class, 'training_enrollment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
