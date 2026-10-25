<?php

use App\Enums\PublishStatus;
use App\Livewire\Forms\ExhibitionForm;
use App\Models\Exhibition;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('展示の編集')] class extends Component {
    #[Locked]
    public Exhibition $exhibition;

    public ExhibitionForm $form;

    /**
     * Mount the component.
     */
    public function mount(Exhibition $exhibition): void
    {
        $this->authorize('update', $exhibition);

        $this->exhibition = $exhibition;
        $this->form->fillFromExhibition($exhibition);
    }

    /**
     * Update the exhibition and its default-locale translation.
     */
    public function save(): void
    {
        $this->authorize('update', $this->exhibition);

        $this->form->update($this->exhibition);

        Flux::toast(variant: 'success', text: '展示を保存しました。');
    }

    /**
     * Switch the exhibition between published and unpublished.
     */
    public function togglePublish(): void
    {
        $this->authorize('update', $this->exhibition);

        $this->exhibition->update([
            'status' => $this->exhibition->isPublished() ? PublishStatus::Unpublished : PublishStatus::Published,
        ]);

        $this->form->status = $this->exhibition->status->value;

        Flux::toast(variant: 'success', text: "展示を「{$this->exhibition->status->label()}」にしました。");
    }

    /**
     * Delete the exhibition and its translations.
     */
    public function deleteExhibition(): void
    {
        $this->authorize('delete', $this->exhibition);

        if ($this->exhibition->artworks()->exists()) {
            Flux::modal('confirm-exhibition-deletion')->close();
            Flux::toast(variant: 'danger', text: '作品が残っている展示は削除できません。先に作品を削除してください。');

            return;
        }

        $this->exhibition->delete();

        Flux::toast(variant: 'success', text: '展示を削除しました。');

        $this->redirectRoute('exhibitions.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">展示の編集</flux:heading>
            <flux:badge size="sm" :color="$exhibition->status->badgeColor()">{{ $exhibition->status->label() }}</flux:badge>
        </div>

        <flux:button wire:click="togglePublish" :icon="$exhibition->isPublished() ? 'eye-slash' : 'eye'">
            {{ $exhibition->isPublished() ? '非公開にする' : '公開する' }}
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-pages::exhibitions.form-fields />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">保存する</flux:button>
            <flux:button variant="ghost" :href="route('exhibitions.index')" wire:navigate>一覧に戻る</flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="space-y-3">
        <flux:heading>展示の削除</flux:heading>
        <flux:text>削除すると元に戻せません。作品が残っている展示は削除できません。</flux:text>

        <flux:modal.trigger name="confirm-exhibition-deletion">
            <flux:button variant="danger">削除する</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="confirm-exhibition-deletion" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">展示を削除しますか？</flux:heading>
                <flux:text class="mt-2">「{{ $exhibition->name() }}」を削除します。この操作は元に戻せません。</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">キャンセル</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteExhibition">削除する</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
