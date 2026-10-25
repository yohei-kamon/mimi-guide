<?php

use App\Enums\PublishStatus;
use App\Livewire\Forms\ArtworkForm;
use App\Livewire\Forms\ArtworkTranslationForm;
use App\Livewire\Forms\AudioGuideForm;
use App\Models\Artwork;
use App\Services\TextToSpeechService;
use Flux\Flux;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('作品の編集')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Artwork $artwork;

    public ArtworkForm $form;

    public ArtworkTranslationForm $translationForm;

    public AudioGuideForm $audioForm;

    /**
     * Mount the component.
     */
    public function mount(Artwork $artwork): void
    {
        $this->authorize('update', $artwork);

        $this->artwork = $artwork;
        $this->form->fillFromArtwork($artwork);

        $translationLocales = ArtworkTranslationForm::localesFor($artwork);

        if ($translationLocales !== []) {
            $this->translationForm->fillFor($artwork, $translationLocales[0]);
        }

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
     * Fill the translation form from the newly selected locale.
     */
    public function updated(string $property): void
    {
        if ($property === 'translationForm.locale') {
            $this->translationForm->fillFor($this->artwork, $this->translationForm->locale);
        }
    }

    /**
     * Add or replace the artwork's translation in the selected locale.
     */
    public function saveTranslation(): void
    {
        $this->authorize('update', $this->artwork);

        $this->translationForm->save($this->artwork);
        $this->artwork->unsetRelation('translations');

        Flux::toast(variant: 'success', text: '翻訳を保存しました。');
    }

    /**
     * Delete the artwork's translation in the selected locale, so the default locale is shown instead.
     */
    public function deleteTranslation(): void
    {
        $this->authorize('update', $this->artwork);

        $this->translationForm->delete($this->artwork);
        $this->artwork->unsetRelation('translations');

        Flux::toast(variant: 'success', text: '翻訳を削除しました。');
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
     * Generate the audio guide in the selected locale from its description with text to speech.
     */
    public function generateAudio(TextToSpeechService $textToSpeech): void
    {
        $this->authorize('update', $this->artwork);

        if ($this->hasUnsavedDescription($this->audioForm->locale)) {
            $this->addError('audioForm.locale', '解説文に保存していない変更があります。先に保存してください。');

            return;
        }

        try {
            $audioGuide = $this->audioForm->generate($this->artwork, $textToSpeech);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            Flux::toast(variant: 'danger', text: '音声の生成に失敗しました。時間をおいて再度お試しください。');

            return;
        }

        if ($audioGuide === null) {
            return;
        }

        $this->artwork->unsetRelation('audioGuides');

        Flux::toast(variant: 'success', text: '音声を生成しました。');
    }

    /**
     * Determine whether the description being edited in the locale differs from the saved one,
     * so audio is not generated from an outdated description.
     */
    private function hasUnsavedDescription(string $locale): bool
    {
        $editedDescription = match ($locale) {
            $this->artwork->exhibition->museum->default_locale => $this->form->description,
            $this->translationForm->locale => $this->translationForm->description,
            default => null,
        };

        if ($editedDescription === null) {
            return false;
        }

        return $editedDescription !== (string) $this->artwork->translations()->where('locale', $locale)->value('description');
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
            <flux:heading>翻訳</flux:heading>
            <flux:text class="mt-1">既定言語以外の作品名・作者・制作年・解説文を登録します。翻訳が無い言語では既定言語で表示します。</flux:text>
        </div>

        @php($translationLocales = \App\Livewire\Forms\ArtworkTranslationForm::localesFor($artwork))

        @if ($translationLocales === [])
            <flux:text>この館の対応言語は既定言語のみです。</flux:text>
        @else
            <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($translationLocales as $locale)
                    <div class="flex items-center gap-4 p-4" wire:key="translation-{{ $locale }}">
                        <div class="w-24 shrink-0 font-medium">{{ config("mimi.locales.{$locale}", $locale) }}</div>

                        @if ($artwork->translations->contains('locale', $locale))
                            <flux:badge size="sm" color="green">登録済み</flux:badge>
                        @else
                            <flux:text>未登録</flux:text>
                        @endif
                    </div>
                @endforeach
            </div>

            <form wire:submit="saveTranslation" class="space-y-6">
                <flux:select wire:model.live="translationForm.locale" label="言語" class="sm:max-w-40">
                    @foreach ($translationLocales as $locale)
                        <flux:select.option :value="$locale">{{ config("mimi.locales.{$locale}", $locale) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="translationForm.title" label="作品名" type="text" required />

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="translationForm.artist" label="作者" type="text" />
                    <flux:input wire:model="translationForm.productionYear" label="制作年" type="text" />
                </div>

                <flux:field>
                    <flux:label>解説文</flux:label>
                    <flux:description>音声の読み上げ原稿も兼ねます</flux:description>
                    <flux:textarea wire:model="translationForm.description" rows="6" />
                    <div
                        class="text-right text-xs text-zinc-500 dark:text-zinc-400"
                        x-text="[...($wire.translationForm.description ?? '')].length + '/10000'"
                        :class="{ 'text-red-500! dark:text-red-400!': [...($wire.translationForm.description ?? '')].length > 10000 }"
                    ></div>
                    <flux:error name="translationForm.description" />
                </flux:field>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="translationForm.locale,saveTranslation">翻訳を保存する</flux:button>

                    @if ($artwork->translations->contains('locale', $translationForm->locale))
                        <flux:button variant="ghost" icon="trash" wire:click="deleteTranslation" wire:confirm="この言語の翻訳を削除しますか？音声は削除されません。">翻訳を削除</flux:button>
                    @endif
                </div>
            </form>
        @endif
    </div>

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

            <div class="flex flex-wrap items-center gap-2">
                <flux:button type="submit" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="audioForm.file,saveAudio,generateAudio">音声を登録する</flux:button>
                <flux:button
                    type="button"
                    icon="sparkles"
                    wire:click="generateAudio"
                    wire:confirm="選択した言語の解説文から音声を生成します。登録済みの音声は差し替わります。よろしいですか？"
                    wire:loading.attr="disabled"
                    wire:target="saveAudio,generateAudio"
                    x-bind:disabled="$wire.$dirty(['form.description', 'translationForm.description'])"
                >
                    <span wire:loading.remove wire:target="generateAudio">解説文から生成する</span>
                    <span wire:loading wire:target="generateAudio">生成中…</span>
                </flux:button>
            </div>
            <flux:text wire:show="$dirty(['form.description', 'translationForm.description'])" wire:cloak class="text-amber-600! dark:text-amber-400!">解説文に保存していない変更があります。先に保存してください。</flux:text>
            <flux:text>「解説文から生成する」は、選択した言語の解説文を読み上げた音声を作ります（数十秒かかることがあります）。</flux:text>
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
