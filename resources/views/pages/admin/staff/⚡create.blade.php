<?php

use App\Actions\SendStaffInvitation;
use App\Livewire\Forms\StaffForm;
use App\Models\Museum;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('館管理者の発行')] class extends Component {
    #[Locked]
    public Museum $museum;

    public StaffForm $form;

    /**
     * Mount the component.
     */
    public function mount(Museum $museum): void
    {
        $this->authorize('update', $museum);

        $this->museum = $museum;
    }

    /**
     * Create the staff account and send the invitation email.
     */
    public function save(SendStaffInvitation $sendInvitation): void
    {
        $this->authorize('update', $this->museum);

        $user = $this->form->store($this->museum);

        $sendInvitation->handle($user);

        Flux::toast(variant: 'success', text: "{$user->email} に招待メールを送りました。");

        $this->redirectRoute('admin.staff.index', $this->museum, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:text>
            <flux:link :href="route('admin.staff.index', $museum)" wire:navigate>{{ $museum->name() }}の館管理者</flux:link>
        </flux:text>
        <flux:heading size="xl" level="1" class="mt-1">館管理者の発行</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="form.name" label="名前" type="text" required autofocus />
        <flux:input wire:model="form.email" label="メールアドレス" type="email" required description="このアドレスに、パスワードを設定するための招待メールを送ります。" />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">発行して招待メールを送る</flux:button>
            <flux:button variant="ghost" :href="route('admin.staff.index', $museum)" wire:navigate>一覧に戻る</flux:button>
        </div>
    </form>
</section>
