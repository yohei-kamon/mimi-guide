<?php

use App\Models\Exhibition;
use App\Models\GuidePass;
use App\Models\Museum;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::visitor')] class extends Component {
    #[Locked]
    public Museum $museum;

    /**
     * Mount the component.
     */
    public function mount(Museum $museum): void
    {
        abort_unless($museum->is_active, 404);

        $this->museum = $museum->load('translations');
    }

    /**
     * Get the exhibitions visitors can see today.
     *
     * @return Collection<int, Exhibition>
     */
    #[Computed]
    public function exhibitions(): Collection
    {
        return $this->museum->exhibitions()
            ->visible()
            ->with('translations')
            ->orderByDesc('starts_on')
            ->get()
            ->each->setRelation('museum', $this->museum);
    }

    /**
     * Get the visitor's valid pass for the museum, if any.
     */
    #[Computed]
    public function validPass(): ?GuidePass
    {
        return $this->museum->validPass($this->museum->passTokenFrom(request()));
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return $this->view()->title($this->museum->name());
    }
}; ?>

<div class="space-y-8">
    <div class="space-y-2">
        <flux:heading size="xl" level="1">{{ $museum->name() }}</flux:heading>

    </div>

    @unless ($museum->isFree())
        @if ($this->validPass !== null)
            <flux:callout icon="check-circle" color="green" text="音声ガイドを利用できます（{{ $this->validPass->expiresAtLabel() }} まで）。" />
        @else
            <x-pages::visitor.purchase-form :museum="$museum" />
        @endif
    @endunless

    <div class="space-y-3">
        <flux:heading size="lg" level="2">開催中の展示</flux:heading>

        @forelse ($this->exhibitions as $exhibition)
            <a href="{{ route('visitor.exhibition', $exhibition) }}" wire:navigate class="block rounded-lg border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                <flux:heading>{{ $exhibition->name() }}</flux:heading>
                <flux:text class="mt-1">{{ $exhibition->periodLabel() }}</flux:text>
            </a>
        @empty
            <flux:text>現在開催中の展示はありません。</flux:text>
        @endforelse
    </div>
</div>
