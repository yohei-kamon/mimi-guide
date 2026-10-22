<?php

use App\Livewire\Forms\MuseumForm;
use App\Models\Museum;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('館の追加')] class extends Component {
    public MuseumForm $form;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', Museum::class);
    }

    /**
     * Register the museum.
     */
    public function save(): void
    {
        $this->authorize('create', Museum::class);

        $this->form->store();

        Flux::toast(variant: 'success', text: '館を追加しました。');

        $this->redirectRoute('admin.museums.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">館の追加</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <x-pages::admin.museums.form-fields />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">追加する</flux:button>
            <flux:button variant="ghost" :href="route('admin.museums.index')" wire:navigate>キャンセル</flux:button>
        </div>
    </form>
</section>
