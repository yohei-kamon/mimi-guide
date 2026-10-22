<?php

use App\Models\Artwork;
use App\Models\Exhibition;
use App\Services\QrCodeService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('QR コードの印刷')] class extends Component {
    #[Locked]
    public Exhibition $exhibition;

    /**
     * Mount the component.
     */
    public function mount(Exhibition $exhibition): void
    {
        $this->authorize('view', $exhibition);

        $exhibition->load('translations');

        $this->exhibition = $exhibition;
    }

    /**
     * Get the exhibition's artworks in playback order, each with its QR code as SVG.
     *
     * @return Collection<int, array{artwork: Artwork, svg: string}>
     */
    #[Computed]
    public function codes(): Collection
    {
        $qrCodes = app(QrCodeService::class);

        return $this->exhibition->artworks()
            ->with('translations')
            ->ordered()
            ->get()
            ->each->setRelation('exhibition', $this->exhibition)
            ->map(fn (Artwork $artwork) => [
                'artwork' => $artwork,
                'svg' => $qrCodes->svg($artwork->publicUrl()),
            ]);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:text>
                <flux:link :href="route('artworks.index', $exhibition)" wire:navigate>{{ $exhibition->name() }}</flux:link>
            </flux:text>
            <flux:heading size="xl" level="1" class="mt-1">QR コードの印刷</flux:heading>
        </div>

        <flux:button variant="primary" icon="printer" x-on:click="window.print()">印刷する</flux:button>
    </div>

    <flux:heading size="lg" class="hidden print:block">{{ $exhibition->name() }}</flux:heading>

    @if ($this->codes->isEmpty())
        <flux:text>まだ作品がありません。</flux:text>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 print:grid-cols-3">
            @foreach ($this->codes as ['artwork' => $artwork, 'svg' => $svg])
                <div wire:key="qr-{{ $artwork->id }}" class="flex break-inside-avoid flex-col items-center gap-2 rounded-lg border border-zinc-200 bg-white p-4 text-center text-zinc-900 dark:border-zinc-700">
                    <div class="w-40">{!! $svg !!}</div>
                    <div class="text-2xl font-semibold tabular-nums">{{ $artwork->guide_number }}</div>
                    <div class="text-sm">{{ $artwork->title() }}</div>

                    @unless ($artwork->isPublished())
                        <flux:badge size="sm" :color="$artwork->status->badgeColor()" class="print:hidden">{{ $artwork->status->label() }}</flux:badge>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif
</section>
