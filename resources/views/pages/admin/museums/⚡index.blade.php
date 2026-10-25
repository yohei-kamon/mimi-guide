<?php

use App\Models\Museum;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('館')] class extends Component {
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Museum::class);
    }

    /**
     * Get all museums with their staff counts.
     *
     * @return Collection<int, Museum>
     */
    #[Computed]
    public function museums(): Collection
    {
        return Museum::query()
            ->with('translations')
            ->withCount('users')
            ->latest('id')
            ->get();
    }

    /**
     * Stop or resume the given museum.
     */
    public function toggleActive(int $museumId): void
    {
        $museum = Museum::findOrFail($museumId);

        $this->authorize('update', $museum);

        $museum->update(['is_active' => ! $museum->is_active]);

        unset($this->museums);

        Flux::toast(variant: 'success', text: $museum->is_active ? "「{$museum->name()}」を再開しました。" : "「{$museum->name()}」を停止しました。");
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">館</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('admin.museums.create')" wire:navigate>
            館を追加
        </flux:button>
    </div>

    @if ($this->museums->isEmpty())
        <flux:text>まだ館がありません。「館を追加」から登録してください。</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>館名</flux:table.column>
                <flux:table.column>slug</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column>料金</flux:table.column>
                <flux:table.column>館管理者</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->museums as $museum)
                    <flux:table.row :key="$museum->id">
                        <flux:table.cell variant="strong">{{ $museum->name() }}</flux:table.cell>
                        <flux:table.cell>{{ $museum->slug }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$museum->is_active ? 'green' : 'zinc'">{{ $museum->is_active ? '稼働中' : '停止中' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $museum->isFree() ? '無料' : number_format($museum->guide_price).'円' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('admin.staff.index', $museum)" wire:navigate>{{ $museum->users_count }}人</flux:link>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" :icon="$museum->is_active ? 'pause' : 'play'" wire:click="toggleActive({{ $museum->id }})">
                                    {{ $museum->is_active ? '停止する' : '再開する' }}
                                </flux:button>
                                <flux:button size="sm" icon="pencil-square" :href="route('admin.museums.edit', $museum)" wire:navigate>
                                    編集
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
