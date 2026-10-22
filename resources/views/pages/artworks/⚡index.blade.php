<?php

use App\Models\Artwork;
use App\Models\Exhibition;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('作品')] class extends Component {
    #[Locked]
    public Exhibition $exhibition;

    /**
     * Mount the component.
     */
    public function mount(Exhibition $exhibition): void
    {
        $this->authorize('viewAny', [Artwork::class, $exhibition]);

        $this->exhibition = $exhibition;
    }

    /**
     * Get the exhibition's artworks in playback order.
     *
     * @return Collection<int, Artwork>
     */
    #[Computed]
    public function artworks(): Collection
    {
        return $this->exhibition->artworks()
            ->with(['exhibition.museum', 'translations'])
            ->ordered()
            ->get();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:text>
                <flux:link :href="route('exhibitions.index')" wire:navigate>展示</flux:link>
                / {{ $exhibition->name() }}
            </flux:text>
            <flux:heading size="xl" level="1" class="mt-1">作品</flux:heading>
        </div>

        <div class="flex gap-2">
            <flux:button icon="pencil-square" :href="route('exhibitions.edit', $exhibition)" wire:navigate>
                展示を編集
            </flux:button>
            <flux:button icon="qr-code" :href="route('exhibitions.qr', $exhibition)" wire:navigate>
                QR を印刷
            </flux:button>
            <flux:button variant="primary" icon="plus" :href="route('artworks.create', $exhibition)" wire:navigate>
                作品を追加
            </flux:button>
        </div>
    </div>

    @if ($this->artworks->isEmpty())
        <flux:text>まだ作品がありません。「作品を追加」から登録してください。</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>並び順</flux:table.column>
                <flux:table.column>ガイド番号</flux:table.column>
                <flux:table.column>作品名</flux:table.column>
                <flux:table.column>作者</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->artworks as $artwork)
                    <flux:table.row :key="$artwork->id">
                        <flux:table.cell>{{ $artwork->sort_order }}</flux:table.cell>
                        <flux:table.cell>{{ $artwork->guide_number }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $artwork->title() }}</flux:table.cell>
                        <flux:table.cell>{{ $artwork->translation()?->artist }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$artwork->status->badgeColor()">{{ $artwork->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="qr-code" :href="route('artworks.qr', $artwork)" wire:navigate>
                                QR
                            </flux:button>
                            <flux:button size="sm" icon="pencil-square" :href="route('artworks.edit', $artwork)" wire:navigate>
                                編集
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
