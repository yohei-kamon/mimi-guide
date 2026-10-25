<?php

use App\Actions\SendStaffInvitation;
use App\Models\Museum;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('館管理者')] class extends Component {
    #[Locked]
    public Museum $museum;

    /**
     * Mount the component.
     */
    public function mount(Museum $museum): void
    {
        $this->authorize('update', $museum);

        $this->museum = $museum;
    }

    /**
     * Get the museum's staff members.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        return $this->museum->users()->orderBy('id')->get();
    }

    /**
     * Send the invitation email to the staff member again with a new link.
     */
    public function resendInvitation(int $userId, SendStaffInvitation $sendInvitation): void
    {
        $this->authorize('update', $this->museum);

        $user = $this->museum->users()->findOrFail($userId);

        $sendInvitation->handle($user);

        Flux::toast(variant: 'success', text: "{$user->email} に招待メールを送りました。");
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:text>
                <flux:link :href="route('admin.museums.index')" wire:navigate>館</flux:link>
                / {{ $museum->name() }}
            </flux:text>
            <flux:heading size="xl" level="1" class="mt-1">館管理者</flux:heading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('admin.staff.create', $museum)" wire:navigate>
            館管理者を発行
        </flux:button>
    </div>

    @if ($this->staff->isEmpty())
        <flux:text>まだ館管理者がいません。「館管理者を発行」から招待してください。</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>名前</flux:table.column>
                <flux:table.column>メールアドレス</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->staff as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($user->email_verified_at === null)
                                <flux:badge size="sm" color="amber">招待中</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">有効</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($user->email_verified_at === null)
                                <flux:button size="sm" icon="envelope" wire:click="resendInvitation({{ $user->id }})">
                                    招待メールを再送
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
