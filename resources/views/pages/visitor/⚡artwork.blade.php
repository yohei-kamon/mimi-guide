<?php

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::visitor')] class extends Component {
    #[Locked]
    public Artwork $artwork;

    /**
     * Mount the component.
     */
    public function mount(Artwork $artwork): void
    {
        $artwork->load('translations', 'audioGuides', 'exhibition.translations', 'exhibition.museum.translations');

        abort_unless($artwork->isVisibleToVisitors(), 404);

        $this->artwork = $artwork;
    }

    /**
     * Get the artwork's translation to display.
     */
    #[Computed]
    public function translation(): ?ArtworkTranslation
    {
        return $this->artwork->translation();
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
        return $this->view()->title($this->artwork->title());
    }
}; ?>

<div class="space-y-6">
    @php($museum = $artwork->exhibition->museum)

    <flux:text>
        <flux:link :href="route('visitor.exhibition', $artwork->exhibition)" wire:navigate>{{ $artwork->exhibition->name() }}</flux:link>
    </flux:text>

    @if ($artwork->image_path !== null)
        <img src="{{ Storage::disk('public')->url($artwork->image_path) }}" alt="{{ $artwork->title() }}" class="w-full rounded-lg object-contain">
    @endif

    <div class="space-y-1">
        <flux:text>No. {{ $artwork->guide_number }}</flux:text>
        <flux:heading size="xl" level="1">{{ $artwork->title() }}</flux:heading>
        <flux:text>
            {{ collect([$this->translation?->artist, $this->translation?->production_year])->filter()->join('、') }}
        </flux:text>
    </div>

    @if ($this->hasAccess)
        @if (filled($this->translation?->description))
            <flux:text class="whitespace-pre-line">{{ $this->translation->description }}</flux:text>
        @endif

        @if ($artwork->audioGuide() !== null)
            <audio controls preload="none" src="{{ route('visitor.artwork.audio', $artwork) }}" class="w-full"></audio>

            <flux:button icon="play" :href="route('visitor.exhibition.play', [$artwork->exhibition, 'from' => $artwork->slug])" class="w-full">
                ここから続けて再生
            </flux:button>
        @endif
    @else
        <x-pages::visitor.purchase-form :museum="$museum" />
    @endif
</div>
