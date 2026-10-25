<?php

use App\Models\Artwork;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::visitor')] class extends Component {
    #[Locked]
    public Artwork $artwork;

    #[Locked]
    public string $locale = '';

    /**
     * Mount the component.
     */
    public function mount(Artwork $artwork): void
    {
        $artwork->load('translations', 'audioGuides', 'exhibition.translations', 'exhibition.museum.translations');

        abort_unless($artwork->isVisibleToVisitors(), 404);

        $this->artwork = $artwork;
        $this->locale = $artwork->exhibition->museum->visitorLocale(request());
    }

    /**
     * Determine whether the visitor can read the description and hear the audio.
     */
    #[Computed]
    public function hasAccess(): bool
    {
        $museum = $this->artwork->exhibition->museum;

        return $museum->grantsAccessWith($museum->passTokenFrom(request()));
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return $this->view()->title($this->artwork->title($this->locale));
    }
}; ?>

<div class="space-y-6">
    @php($museum = $artwork->exhibition->museum)

    <flux:text>
        <flux:link :href="route('visitor.exhibition', $artwork->exhibition)" wire:navigate>{{ $artwork->exhibition->name($locale) }}</flux:link>
    </flux:text>

    @if ($artwork->image_path !== null)
        <img src="{{ Storage::disk('public')->url($artwork->image_path) }}" alt="{{ $artwork->title($locale) }}" class="w-full rounded-lg object-contain">
    @endif

    <div class="space-y-1">
        <flux:text>No. {{ $artwork->guide_number }}</flux:text>
        <flux:heading size="xl" level="1">{{ $artwork->title($locale) }}</flux:heading>
        <flux:text>
            {{ collect([$artwork->translated('artist', $locale), $artwork->translated('production_year', $locale)])->filter()->join('、') }}
        </flux:text>
    </div>

    <x-pages::visitor.locale-switcher :museum="$museum" :locale="$locale" />

    @if ($this->hasAccess)
        @if (filled($artwork->translated('description', $locale)))
            <flux:text class="whitespace-pre-line">{{ $artwork->translated('description', $locale) }}</flux:text>
        @endif

        @if ($artwork->audioGuide($locale) !== null)
            @if ($artwork->audioGuide($locale)->locale !== $locale)
                <flux:text>この言語の音声はありません。</flux:text>
            @endif

            <audio controls preload="none" src="{{ route('visitor.artwork.audio', $artwork) }}" class="w-full"></audio>

            <flux:button icon="play" :href="route('visitor.exhibition.play', [$artwork->exhibition, 'from' => $artwork->slug])" class="w-full">
                ここから続けて再生
            </flux:button>
        @endif
    @else
        <x-pages::visitor.purchase-form :museum="$museum" />
    @endif
</div>
