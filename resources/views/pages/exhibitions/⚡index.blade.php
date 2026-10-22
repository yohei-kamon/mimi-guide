<?php

use App\Models\Exhibition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('展示')] class extends Component {
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Exhibition::class);
    }

    /**
     * Get the exhibitions of the user's museum.
     *
     * @return Collection<int, Exhibition>
     */
    #[Computed]
    public function exhibitions(): Collection
    {
        return Auth::user()->museum->exhibitions()
            ->with(['museum', 'translations'])
            ->latest('id')
            ->get();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">展示</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('exhibitions.create')" wire:navigate>
            展示を追加
        </flux:button>
    </div>

    @if ($this->exhibitions->isEmpty())
        <flux:text>まだ展示がありません。「展示を追加」から登録してください。</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>展示名</flux:table.column>
                <flux:table.column>期間</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->exhibitions as $exhibition)
                    <flux:table.row :key="$exhibition->id">
                        <flux:table.cell variant="strong">{{ $exhibition->name() }}</flux:table.cell>
                        <flux:table.cell>{{ $exhibition->periodLabel() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$exhibition->status->badgeColor()">{{ $exhibition->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" icon="pencil-square" :href="route('exhibitions.edit', $exhibition)" wire:navigate>
                                編集
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
