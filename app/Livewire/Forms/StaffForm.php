<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\Museum;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffForm extends Form
{
    public string $name = '';

    public string $email = '';

    /**
     * Get the validation rules for the staff form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ];
    }

    /**
     * Get the display names of the fields for error messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => '名前',
            'email' => 'メールアドレス',
        ];
    }

    /**
     * Validate the form and create a staff member of the museum with a random password.
     * The password is set by the staff member from the invitation email.
     */
    public function store(Museum $museum): User
    {
        $this->validate();

        $user = new User;
        $user->forceFill([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Str::random(40),
            'role' => UserRole::Staff,
            'museum_id' => $museum->id,
        ])->save();

        return $user;
    }
}
