<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    /**
     * Get the display name of the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'プラットフォーム管理者',
            self::Staff => '館管理者',
        };
    }

    /**
     * Get the path the user is sent to after logging in.
     */
    public function homePath(): string
    {
        return match ($this) {
            self::Admin => route('admin.museums.index', absolute: false),
            self::Staff => route('dashboard', absolute: false),
        };
    }
}
