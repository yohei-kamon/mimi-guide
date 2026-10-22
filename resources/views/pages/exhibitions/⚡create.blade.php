<?php

use App\Livewire\Forms\ExhibitionForm;
use App\Models\Exhibition;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('展示の追加')] class extends Component {
    public ExhibitionForm $form;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', Exhibition::class);
    }

    /**
     * Create the exhibition in the user's museum.
     */
    public function save(): void
    {
        $this->authorize('create', Exhibition::class);

        $this->form->store(Auth::user()->museum);

        Flux::toast(variant: 'success', text: '展示を追加しました。');

        $this->redirectRoute('exhibitions.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">展示の追加</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <x-pages::exhibitions.form-fields />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">追加する</flux:button>
            <flux:button variant="ghost" :href="route('exhibitions.index')" wire:navigate>キャンセル</flux:button>
        </div>
    </form>
</section>
