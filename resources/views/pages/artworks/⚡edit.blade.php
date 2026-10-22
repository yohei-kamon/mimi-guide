<?php

use App\Enums\PublishStatus;
use App\Livewire\Forms\ArtworkForm;
use App\Livewire\Forms\AudioGuideForm;
use App\Models\Artwork;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('作品の編集')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Artwork $artwork;

    public ArtworkForm $form;

    public AudioGuideForm $audioForm;

    /**
     * Mount the component.
     */
    public function mount(Artwork $artwork): void
    {
        $this->authorize('update', $artwork);

        $this->artwork = $artwork;
        $this->form->fillFromArtwork($artwork);
        $this->audioForm->locale = $artwork->exhibition->museum->default_locale;
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
     * Add or replace the audio guide in the selected locale.
     */
    public function saveAudio(): void
    {
        $this->authorize('update', $this->artwork);

        $this->audioForm->save($this->artwork);
        $this->artwork->unsetRelation('audioGuides');

        Flux::toast(variant: 'success', text: '音声を登録しました。');
    }

    /**
     * Delete one of the artwork's audio guides and its file.
     */
    public function deleteAudio(int $audioGuideId): void
    {
        $this->authorize('update', $this->artwork);

        $this->artwork->audioGuides()->findOrFail($audioGuideId)->deleteWithFile();
        $this->artwork->unsetRelation('audioGuides');

        Flux::toast(variant: 'success', text: '音声を削除しました。');
    }

    /**
     * Delete the artwork with its translations, audio guides and audio files.
     */
    public function deleteArtwork(): void
    {
        $this->authorize('delete', $this->artwork);

        $exhibition = $this->artwork->exhibition;

        $this->artwork->deleteWithFiles();

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

        <div class="flex gap-2">
            <flux:button icon="qr-code" :href="route('artworks.qr', $artwork)" wire:navigate>QR コード</flux:button>
            <flux:button wire:click="togglePublish" :icon="$artwork->isPublished() ? 'eye-slash' : 'eye'">
                {{ $artwork->isPublished() ? '非公開にする' : '公開する' }}
            </flux:button>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-pages::artworks.form-fields />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">保存する</flux:button>
            <flux:button variant="ghost" :href="route('artworks.index', $artwork->exhibition)" wire:navigate>一覧に戻る</flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="space-y-4">
        <div>
            <flux:heading>音声</flux:heading>
            <flux:text class="mt-1">言語ごとに1件まで登録できます。同じ言語で登録すると差し替えます。</flux:text>
        </div>

        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($artwork->exhibition->museum->locales as $locale)
                @php($audioGuide = $artwork->audioGuides->firstWhere('locale', $locale))

                <div class="flex flex-wrap items-center gap-4 p-4" wire:key="audio-{{ $locale }}">
                    <div class="w-24 shrink-0 font-medium">{{ config("mimi.locales.{$locale}", $locale) }}</div>

                    @if ($audioGuide !== null)
                        <audio controls preload="none" src="{{ route('audio-guides.preview', [$audioGuide, 'v' => $audioGuide->updated_at?->timestamp]) }}" class="min-w-0 flex-1"></audio>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteAudio({{ $audioGuide->id }})" wire:confirm="この言語の音声を削除しますか？">削除</flux:button>
                    @else
                        <flux:text class="flex-1">未登録</flux:text>
                    @endif
                </div>
            @endforeach
        </div>

        <form wire:submit="saveAudio" class="space-y-4">
            <div class="grid gap-6 sm:grid-cols-[10rem_1fr]">
                <flux:select wire:model="audioForm.locale" label="言語">
                    @foreach ($artwork->exhibition->museum->locales as $locale)
                        <flux:select.option :value="$locale">{{ config("mimi.locales.{$locale}", $locale) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="audioForm.file" type="file" label="音声ファイル" accept=".mp3,.m4a,audio/mpeg,audio/mp4" description="MP3 / M4A、20MB まで" />
            </div>

            <flux:button type="submit" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="audioForm.file,saveAudio">音声を登録する</flux:button>
        </form>
    </div>

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
