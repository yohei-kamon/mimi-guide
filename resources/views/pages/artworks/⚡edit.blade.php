<?php

use App\Enums\PublishStatus;
use App\Livewire\Forms\ArtworkForm;
use App\Models\Artwork;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('作品の編集')] class extends Component {
    #[Locked]
    public Artwork $artwork;

    public ArtworkForm $form;

    /**
     * Mount the component.
     */
    public function mount(Artwork $artwork): void
    {
        $this->authorize('update', $artwork);

        $this->artwork = $artwork;
        $this->form->fillFromArtwork($artwork);
    }

    /**
     * Update the artwork and its default-locale translation.
     */
    public function save(): void
    {
        $this->authorize('update', $this->artwork);

        $this->form->update($this->artwork);

        Flux::toast(variant: 'success', text: '作品を保存しました。');
    }

    /**
     * Switch the artwork between published and unpublished.
     */
    public function togglePublish(): void
    {
        $this->authorize('update', $this->artwork);

        $this->artwork->update([
            'status' => $this->artwork->isPublished() ? PublishStatus::Unpublished : PublishStatus::Published,
        ]);

        $this->form->status = $this->artwork->status->value;

        Flux::toast(variant: 'success', text: "作品を「{$this->artwork->status->label()}」にしました。");
    }

    /**
     * Delete the artwork and its translations.
     */
    public function deleteArtwork(): void
    {
        $this->authorize('delete', $this->artwork);

        $exhibition = $this->artwork->exhibition;

        $this->artwork->delete();

        Flux::toast(variant: 'success', text: '作品を削除しました。');

        $this->redirectRoute('artworks.index', $exhibition, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:text>
                <flux:link :href="route('artworks.index', $artwork->exhibition)" wire:navigate>{{ $artwork->exhibition->name() }}</flux:link>
            </flux:text>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading size="xl" level="1">作品の編集</flux:heading>
                <flux:badge size="sm" :color="$artwork->status->badgeColor()">{{ $artwork->status->label() }}</flux:badge>
            </div>
        </div>

        <flux:button wire:click="togglePublish" :icon="$artwork->isPublished() ? 'eye-slash' : 'eye'">
            {{ $artwork->isPublished() ? '非公開にする' : '公開する' }}
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-pages::artworks.form-fields />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">保存する</flux:button>
            <flux:button variant="ghost" :href="route('artworks.index', $artwork->exhibition)" wire:navigate>一覧に戻る</flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="space-y-3">
        <flux:heading>作品の削除</flux:heading>
        <flux:text>削除すると元に戻せません。</flux:text>

        <flux:modal.trigger name="confirm-artwork-deletion">
            <flux:button variant="danger">削除する</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="confirm-artwork-deletion" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">作品を削除しますか？</flux:heading>
                <flux:text class="mt-2">「{{ $artwork->title() }}」を削除します。この操作は元に戻せません。</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">キャンセル</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteArtwork">削除する</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
