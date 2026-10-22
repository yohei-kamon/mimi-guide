<flux:input wire:model="form.title" label="作品名" type="text" required autofocus />

<div class="grid gap-6 sm:grid-cols-2">
    <flux:input wire:model="form.artist" label="作者" type="text" />
    <flux:input wire:model="form.productionYear" label="制作年" type="text" placeholder="例: 1831年頃、江戸時代" />
</div>

<flux:textarea wire:model="form.description" label="解説文" rows="6" description="音声の読み上げ原稿も兼ねます" />

<div class="grid gap-6 sm:grid-cols-2">
    <flux:input wire:model="form.guideNumber" label="ガイド番号" type="number" min="1" required description="展示の中で重複しない番号" />
    <flux:input wire:model="form.sortOrder" label="並び順" type="number" min="0" description="小さい順に連続再生します。同じ値はガイド番号順" />
</div>

<flux:select wire:model="form.status" label="公開状態">
    @foreach (\App\Enums\PublishStatus::cases() as $publishStatus)
        <flux:select.option :value="$publishStatus->value">{{ $publishStatus->label() }}</flux:select.option>
    @endforeach
</flux:select>
