<?php

namespace App\Actions;

use App\Mail\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendStaffInvitation
{
    /**
     * Send the staff member an invitation to set their password, using a password reset token.
     */
    public function handle(User $user): void
    {
        $token = Password::broker()->createToken($user);

        Mail::to($user)->send(new StaffInvitation($user->loadMissing('museum.translations'), $token));
    }
}
