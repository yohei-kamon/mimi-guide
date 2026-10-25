@props(['museum'])

<flux:callout icon="lock-closed">
    <flux:callout.heading>解説と音声は音声ガイドの購入が必要です</flux:callout.heading>
    <flux:callout.text>購入すると、支払いから24時間、この館の全ての展示で利用できます。会員登録は不要です。</flux:callout.text>
    <x-slot name="actions">
        <form method="POST" action="{{ route('visitor.passes.store', $museum) }}">
            @csrf
            <flux:button type="submit" variant="primary">音声ガイドを購入（{{ number_format($museum->guide_price) }}円）</flux:button>
        </form>
    </x-slot>
</flux:callout>
