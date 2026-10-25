<?php

use App\Livewire\Forms\MuseumForm;
use App\Models\Museum;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('館の編集')] class extends Component {
    #[Locked]
    public Museum $museum;

    public MuseumForm $form;

    /**
     * Mount the component.
     */
    public function mount(Museum $museum): void
    {
        $this->authorize('update', $museum);

        $this->museum = $museum;
        $this->form->fillFromMuseum($museum);
    }

    /**
     * Update the museum and its default-locale name.
     */
    public function save(): void
    {
        $this->authorize('update', $this->museum);

        $this->form->update($this->museum);

        Flux::toast(variant: 'success', text: '館を保存しました。');
    }

    /**
     * Stop or resume the museum.
     */
    public function toggleActive(): void
    {
        $this->authorize('update', $this->museum);

        $this->museum->update(['is_active' => ! $this->museum->is_active]);

        Flux::toast(variant: 'success', text: $this->museum->is_active ? '館を再開しました。' : '館を停止しました。');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">館の編集</flux:heading>
            <flux:badge size="sm" :color="$museum->is_active ? 'green' : 'zinc'">{{ $museum->is_active ? '稼働中' : '停止中' }}</flux:badge>
        </div>

        <flux:button wire:click="toggleActive" :icon="$museum->is_active ? 'pause' : 'play'">
            {{ $museum->is_active ? '停止する' : '再開する' }}
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-pages::admin.museums.form-fields />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">保存する</flux:button>
            <flux:button variant="ghost" :href="route('admin.museums.index')" wire:navigate>一覧に戻る</flux:button>
        </div>
    </form>

    <flux:text>停止すると、来館者からは館・展示・作品が見えなくなります。データは残り、再開すると元に戻ります。</flux:text>
</section>
