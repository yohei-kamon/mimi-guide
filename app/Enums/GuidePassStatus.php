<?php

namespace App\Enums;

enum GuidePassStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';

    /**
     * Get the display name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => '支払い待ち',
            self::Paid => '支払い済み',
        };
    }
}
