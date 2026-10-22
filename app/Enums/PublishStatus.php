<?php

namespace App\Enums;

enum PublishStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';

    /**
     * Get the display name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Published => '公開',
            self::Unpublished => '非公開',
        };
    }

    /**
     * Get the Flux badge color for the status.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Published => 'green',
            self::Unpublished => 'amber',
        };
    }
}
