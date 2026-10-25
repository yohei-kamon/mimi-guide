<?php

use App\Models\Artwork;
use App\Models\Exhibition;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::visitor')] class extends Component {
    #[Locked]
    public Exhibition $exhibition;

    #[Locked]
    public string $locale = '';

    public string $guideNumber = '';

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
        $this->locale = $exhibition->museum->visitorLocale(request());
    }

    /**
     * Get the exhibition's published artworks in playback order.
     *
     * @return Collection<int, Artwork>
     */
    #[Computed]
    public function artworks(): Collection
    {
        return $this->exhibition->artworks()
            ->published()
            ->ordered()
            ->with('translations')
            ->get()
            ->each->setRelation('exhibition', $this->exhibition);
    }

    /**
     * Open the published artwork with the entered guide number in this exhibition.
     */
    public function openByGuideNumber(): void
    {
        $this->validate(
            ['guideNumber' => ['required', 'integer', 'min:1']],
            attributes: ['guideNumber' => 'ガイド番号'],
        );

        $artwork = $this->exhibition->artworks()
            ->published()
            ->where('guide_number', (int) $this->guideNumber)
            ->first();

        if ($artwork === null) {
            $this->addError('guideNumber', 'この番号の作品はありません。');

            return;
        }

        $this->redirectRoute('visitor.artwork', $artwork, navigate: true);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return $this->view()->title($this->exhibition->name($this->locale));
    }
}; ?>

<div class="space-y-8">
    <div class="space-y-2">
        <flux:text>
            <flux:link :href="route('visitor.museum', $exhibition->museum)" wire:navigate>{{ $exhibition->museum->name($locale) }}</flux:link>
        </flux:text>
        <flux:heading size="xl" level="1">{{ $exhibition->name($locale) }}</flux:heading>
        <flux:text>{{ $exhibition->periodLabel() }}</flux:text>

        @if (filled($exhibition->translated('description', $locale)))
            <flux:text class="whitespace-pre-line">{{ $exhibition->translated('description', $locale) }}</flux:text>
        @endif

        <x-pages::visitor.locale-switcher :museum="$exhibition->museum" :locale="$locale" />
    </div>

    @if ($this->artworks->isNotEmpty())
        <flux:button variant="primary" icon="play" :href="route('visitor.exhibition.play', $exhibition)" class="w-full">
            最初から続けて再生
        </flux:button>
    @endif

    <form wire:submit="openByGuideNumber" class="flex items-end gap-2">
        <div class="flex-1">
            <flux:input wire:model="guideNumber" label="ガイド番号" type="number" inputmode="numeric" min="1" />
        </div>
        <flux:button type="submit" variant="primary">開く</flux:button>
    </form>

    <div class="space-y-3">
        <flux:heading size="lg" level="2">作品</flux:heading>

        @forelse ($this->artworks as $artwork)
            <a href="{{ route('visitor.artwork', $artwork) }}" wire:navigate class="flex items-center gap-4 rounded-lg border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <span class="w-10 shrink-0 text-center text-lg font-semibold tabular-nums text-zinc-500">{{ $artwork->guide_number }}</span>
                <flux:heading>{{ $artwork->title($locale) }}</flux:heading>
            </a>
        @empty
            <flux:text>公開中の作品はありません。</flux:text>
        @endforelse
    </div>
</div>
