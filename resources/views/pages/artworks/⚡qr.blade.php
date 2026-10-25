<?php

use App\Models\Artwork;
use App\Services\QrCodeService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('QR コード')] class extends Component {
    #[Locked]
    public Artwork $artwork;

    /**
     * Mount the component.
     */
    public function mount(Artwork $artwork): void
    {
        $this->authorize('view', $artwork);

        $artwork->load('translations', 'exhibition.translations');

        $this->artwork = $artwork;
    }

    /**
     * Get the QR code of the artwork's visitor page as SVG.
     */
    #[Computed]
    public function svg(): string
    {
        return app(QrCodeService::class)->svg($this->artwork->publicUrl());
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:text>
            <flux:link :href="route('artworks.index', $artwork->exhibition)" wire:navigate>{{ $artwork->exhibition->name() }}</flux:link>
            / <flux:link :href="route('artworks.edit', $artwork)" wire:navigate>{{ $artwork->title() }}</flux:link>
        </flux:text>
        <flux:heading size="xl" level="1" class="mt-1">QR コード</flux:heading>
    </div>

    @unless ($artwork->isPublished())
        <flux:callout icon="exclamation-triangle" color="amber" text="この作品は公開されていないため、読み取っても来館者には表示されません。" />
    @endunless

    <div class="w-60 rounded-lg bg-white p-2">{!! $this->svg !!}</div>

    <div class="space-y-1">
        <flux:text>作品ページの URL</flux:text>
        <flux:text variant="strong" class="break-all">{{ $artwork->publicUrl() }}</flux:text>
    </div>

    <div class="flex gap-2">
        <flux:button
            icon="arrow-down-tray"
            href="data:image/svg+xml;base64,{{ base64_encode($this->svg) }}"
            download="qr-{{ $artwork->exhibition_id }}-{{ $artwork->guide_number }}.svg"
        >
            SVG をダウンロード
        </flux:button>
        <flux:button icon="printer" :href="route('exhibitions.qr', $artwork->exhibition)">展示の QR をまとめて印刷</flux:button>
    </div>
</section>
