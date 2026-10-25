<flux:input wire:model="form.name" label="展示名" type="text" required autofocus />

<flux:textarea wire:model="form.description" label="説明" rows="4" />

<div class="grid gap-6 sm:grid-cols-2">
    <flux:input wire:model="form.startsOn" label="開始日" type="date" description="空欄なら開始日の制限なし" />
    <flux:input wire:model="form.endsOn" label="終了日" type="date" description="空欄なら常設展" />
</div>

<flux:select wire:model="form.status" label="公開状態">
    @foreach (\App\Enums\PublishStatus::cases() as $publishStatus)
        <flux:select.option :value="$publishStatus->value">{{ $publishStatus->label() }}</flux:select.option>
    @endforeach
</flux:select>
