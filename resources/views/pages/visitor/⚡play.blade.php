<?php

use App\Models\Artwork;
use App\Models\Exhibition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::visitor')] class extends Component {
    #[Locked]
    public Exhibition $exhibition;

    #[Locked]
    public string $from = '';

    /**
     * Mount the component.
     */
    public function mount(Exhibition $exhibition): void
    {
        $exhibition->load('translations', 'museum.translations');

        abort_unless(
            $exhibition->museum->is_active && Exhibition::visible()->whereKey($exhibition->id)->exists(),
            404,
        );

        $this->exhibition = $exhibition;
        $this->from = (string) request()->query('from', '');
    }

    /**
     * Get the published artworks that have audio, in playback order, as the data the player needs.
     *
     * @return list<array{slug: string, guideNumber: int, title: string, credit: string, description: string, imageUrl: string|null, audioUrl: string, artworkUrl: string}>
     */
    #[Computed]
    public function playlist(): array
    {
        return $this->exhibition->artworks()
            ->published()
            ->ordered()
            ->with('translations', 'audioGuides')
            ->get()
            ->each->setRelation('exhibition', $this->exhibition)
            ->filter(fn (Artwork $artwork) => $artwork->audioGuide() !== null)
            ->map(fn (Artwork $artwork) => [
                'slug' => $artwork->slug,
                'guideNumber' => $artwork->guide_number,
                'title' => $artwork->title(),
                'credit' => collect([$artwork->translation()?->artist, $artwork->translation()?->production_year])->filter()->join('、'),
                'description' => $artwork->translation()->description ?? '',
                'imageUrl' => $artwork->image_path !== null ? Storage::disk('public')->url($artwork->image_path) : null,
                'audioUrl' => route('visitor.artwork.audio', $artwork),
                'artworkUrl' => route('visitor.artwork', $artwork),
            ])
            ->values()
            ->all();
    }

    /**
     * Get the position in the playlist to start from: the requested artwork, or the first one.
     */
    #[Computed]
    public function startIndex(): int
    {
        $index = array_search($this->from, array_column($this->playlist, 'slug'), true);

        return $index === false ? 0 : $index;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return $this->view()->title($this->exhibition->name());
    }
}; ?>

<div class="space-y-6">
    @php($museum = $exhibition->museum)

    <flux:text>
        <flux:link :href="route('visitor.exhibition', $exhibition)" wire:navigate>{{ $exhibition->name() }}</flux:link>
    </flux:text>

    @if (! $museum->isFree())
        <flux:callout icon="lock-closed">
            <flux:callout.heading>連続再生は音声ガイドの購入が必要です</flux:callout.heading>
            <flux:callout.text>音声ガイド {{ number_format($museum->guide_price) }}円</flux:callout.text>
        </flux:callout>
    @elseif ($this->playlist === [])
        <flux:text>音声のある作品はまだありません。</flux:text>
    @else
        <div
            wire:ignore
            x-data="{
                playlist: @js($this->playlist),
                index: @js($this->startIndex),
                playing: false,
                finished: false,
                get current() { return this.playlist[this.index] },
                load(index) {
                    this.index = index
                    this.finished = false
                    this.$refs.audio.src = this.current.audioUrl
                    this.$refs.audio.play()
                },
                toggle() {
                    const audio = this.$refs.audio
                    if (this.finished) { return this.load(0) }
                    if (! audio.getAttribute('src')) { return this.load(this.index) }
                    audio.paused ? audio.play() : audio.pause()
                },
                previous() { if (this.index > 0) { this.load(this.index - 1) } },
                next() { if (this.index < this.playlist.length - 1) { this.load(this.index + 1) } },
                ended() {
                    if (this.index < this.playlist.length - 1) { return this.load(this.index + 1) }
                    this.finished = true
                },
            }"
            class="space-y-6"
        >
            <audio
                x-ref="audio"
                preload="none"
                x-on:play="playing = true"
                x-on:pause="playing = false"
                x-on:ended="ended()"
            ></audio>

            <template x-if="current.imageUrl">
                <img :src="current.imageUrl" :alt="current.title" class="w-full rounded-lg object-contain">
            </template>

            <div class="space-y-1">
                <flux:text x-text="`No. ${current.guideNumber}`"></flux:text>
                <flux:heading size="xl" level="1" x-text="current.title"></flux:heading>
                <flux:text x-text="current.credit"></flux:text>
            </div>

            <div x-show="finished" x-cloak>
                <flux:callout icon="check-circle" color="green" text="この展示のガイドは以上です。" />
            </div>

            <div class="flex items-center justify-center gap-4">
                <flux:button icon="backward" x-on:click="previous()" x-bind:disabled="index === 0" aria-label="前の作品" />
                <flux:button variant="primary" x-on:click="toggle()" class="min-w-32">
                    <span x-show="! playing">再生</span>
                    <span x-show="playing" x-cloak>一時停止</span>
                </flux:button>
                <flux:button icon="forward" x-on:click="next()" x-bind:disabled="index === playlist.length - 1" aria-label="次の作品" />
            </div>

            <flux:text class="whitespace-pre-line" x-show="current.description" x-text="current.description"></flux:text>

            <div class="space-y-2">
                <flux:heading size="lg" level="2">作品一覧</flux:heading>

                <template x-for="(artwork, position) in playlist" :key="artwork.slug">
                    <button
                        type="button"
                        x-on:click="load(position)"
                        class="flex w-full items-center gap-4 rounded-lg border p-3 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800"
                        :class="position === index ? 'border-zinc-800 dark:border-white' : 'border-zinc-200 dark:border-zinc-700'"
                    >
                        <span class="w-10 shrink-0 text-center text-lg font-semibold tabular-nums text-zinc-500" x-text="artwork.guideNumber"></span>
                        <span class="font-medium text-zinc-800 dark:text-white" x-text="artwork.title"></span>
                    </button>
                </template>
            </div>
        </div>
    @endif
</div>
