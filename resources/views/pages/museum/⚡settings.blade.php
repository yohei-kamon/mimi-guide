<?php

use App\Models\Museum;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('館の設定')] class extends Component {
    #[Locked]
    public Museum $museum;

    public string $guidePrice = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $museum = Auth::user()->museum;

        abort_if($museum === null, 403);

        $this->authorize('updatePrice', $museum);

        $this->museum = $museum;
        $this->guidePrice = (string) $museum->guide_price;
    }

    /**
     * Save the audio guide price. Stripe cannot charge less than 50 yen, so a paid price starts there.
     */
    public function save(): void
    {
        $this->authorize('updatePrice', $this->museum);

        $validated = $this->validate(
            [
                'guidePrice' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:100000',
                    function (string $attribute, mixed $value, Closure $fail) {
                        if ((int) $value > 0 && (int) $value < 50) {
                            $fail('有料にする場合、料金は50円以上にしてください。');
                        }
                    },
                ],
            ],
            attributes: ['guidePrice' => '音声ガイド料金'],
        );

        $this->museum->update(['guide_price' => (int) $validated['guidePrice']]);

        Flux::toast(variant: 'success', text: '料金を保存しました。');
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:text>{{ $museum->name() }}</flux:text>
        <flux:heading size="xl" level="1" class="mt-1">館の設定</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>音声ガイド料金</flux:label>
            <flux:description>0円にすると無料になり、来館者は購入せずに解説と音声を利用できます。</flux:description>
            <flux:input.group>
                <flux:input wire:model="guidePrice" type="number" inputmode="numeric" min="0" step="1" />
                <flux:input.group.suffix>円</flux:input.group.suffix>
            </flux:input.group>
            <flux:error name="guidePrice" />
        </flux:field>

        <flux:text>変更後の料金は、これから購入されるガイドパスに適用されます。購入済みのパスの金額は変わりません。</flux:text>

        <flux:button variant="primary" type="submit">保存する</flux:button>
    </form>
</section>
