<?php

use App\Actions\ConfirmGuidePassPayment;
use App\Enums\GuidePassStatus;
use App\Models\Exhibition;
use App\Models\GuidePass;
use App\Services\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::visitor')] class extends Component {
    #[Locked]
    public GuidePass $guidePass;

    #[Locked]
    public string $locale = '';

    /**
     * Mount the component. Confirm the payment with Stripe in case the page opens before the webhook arrives.
     */
    public function mount(CheckoutService $checkout, ConfirmGuidePassPayment $confirmPayment): void
    {
        $sessionId = (string) request()->query('session_id', '');

        $guidePass = GuidePass::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->with('museum.translations')
            ->first();

        abort_if($sessionId === '' || $guidePass === null, 404);

        if ($guidePass->status === GuidePassStatus::Pending) {
            $confirmPayment->handle($guidePass, $checkout->retrieveSession($sessionId));
        }

        Cookie::queue($guidePass->museum->passCookie($guidePass));

        $this->guidePass = $guidePass;
        $this->locale = $guidePass->museum->visitorLocale(request());
    }

    /**
     * Get the museum's exhibitions visitors can see today, to start continuous playback.
     *
     * @return Collection<int, Exhibition>
     */
    #[Computed]
    public function exhibitions(): Collection
    {
        return $this->guidePass->museum->exhibitions()
            ->visible()
            ->with('translations')
            ->get()
            ->each->setRelation('museum', $this->guidePass->museum);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return $this->view()->title('音声ガイドの購入');
    }
}; ?>

<div class="space-y-6">
    @php($museum = $guidePass->museum)

    <flux:text>{{ $museum->name($locale) }}</flux:text>

    @if ($guidePass->isValid())
        <flux:callout icon="check-circle" color="green">
            <flux:callout.heading>購入が完了しました</flux:callout.heading>
            <flux:callout.text>{{ $guidePass->expiresAtLabel() }} まで、この館の全ての展示で解説と音声を利用できます。購入完了のメールにも、このパスを開き直すための URL をお送りします。</flux:callout.text>
        </flux:callout>

        <div class="space-y-3">
            <flux:button variant="primary" :href="route('visitor.museum', $museum)" class="w-full">館のトップへ</flux:button>

            @foreach ($this->exhibitions as $exhibition)
                <flux:button icon="play" :href="route('visitor.exhibition.play', $exhibition)" class="w-full">
                    {{ $exhibition->name($locale) }}を続けて再生
                </flux:button>
            @endforeach
        </div>
    @elseif ($guidePass->status === GuidePassStatus::Paid)
        <flux:callout icon="clock" text="このパスは有効期限が切れています。" />
        <flux:button :href="route('visitor.museum', $museum)">館のトップへ</flux:button>
    @else
        <flux:callout icon="clock">
            <flux:callout.heading>支払いを確認しています</flux:callout.heading>
            <flux:callout.text>しばらくしてから、このページを再読み込みしてください。</flux:callout.text>
        </flux:callout>
        <flux:button :href="route('visitor.museum', $museum)">館のトップへ</flux:button>
    @endif
</div>
