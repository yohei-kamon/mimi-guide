<flux:input wire:model="form.name" label="館名" type="text" required autofocus description="既定言語での館名" />

<flux:input wire:model="form.slug" label="公開用 slug" type="text" required description="館トップの URL（/m/〇〇）に使います。英小文字・数字・ハイフン" />

<flux:checkbox.group wire:model="form.locales" label="対応言語">
    @foreach (config('mimi.locales') as $localeCode => $localeName)
        <flux:checkbox :value="$localeCode" :label="$localeName" />
    @endforeach
</flux:checkbox.group>

<flux:select wire:model="form.defaultLocale" label="既定言語" description="対応言語の中から選びます">
    @foreach (config('mimi.locales') as $localeCode => $localeName)
        <flux:select.option :value="$localeCode">{{ $localeName }}</flux:select.option>
    @endforeach
</flux:select>
