<?php

use App\Livewire\Forms\ArtworkForm;
use App\Models\Artwork;
use App\Models\Exhibition;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('作品の追加')] class extends Component {
    #[Locked]
    public Exhibition $exhibition;

    public ArtworkForm $form;

    /**
     * Mount the component.
     */
    public function mount(Exhibition $exhibition): void
    {
        $this->authorize('create', [Artwork::class, $exhibition]);

        $this->exhibition = $exhibition;
        $this->form->suggestGuideNumber($exhibition);
    }

    /**
     * Add the artwork to the exhibition.
     */
    public function save(): void
    {
        $this->authorize('create', [Artwork::class, $this->exhibition]);

        $this->form->store($this->exhibition);

        Flux::toast(variant: 'success', text: '作品を追加しました。');

        $this->redirectRoute('artworks.index', $this->exhibition, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:text>
            <flux:link :href="route('artworks.index', $exhibition)" wire:navigate>{{ $exhibition->name() }}</flux:link>
        </flux:text>
        <flux:heading size="xl" level="1" class="mt-1">作品の追加</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-pages::artworks.form-fields />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">追加する</flux:button>
            <flux:button variant="ghost" :href="route('artworks.index', $exhibition)" wire:navigate>キャンセル</flux:button>
        </div>
    </form>
</section>
