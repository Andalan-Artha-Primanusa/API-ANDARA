<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Returned => 'Dikembalikan',
        };
    }
}
