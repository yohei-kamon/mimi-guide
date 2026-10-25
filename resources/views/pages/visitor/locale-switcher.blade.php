@props(['museum', 'locale'])

@if (count($museum->locales) > 1)
    <nav aria-label="言語" class="flex flex-wrap gap-2">
        @foreach ($museum->locales as $code)
            <flux:button
                size="sm"
                :variant="$code === $locale ? 'primary' : 'ghost'"
                :href="request()->fullUrlWithQuery(['lang' => $code])"
                :aria-current="$code === $locale ? 'true' : null"
                lang="{{ $code }}"
            >
                {{ config("mimi.locales.{$code}", $code) }}
            </flux:button>
        @endforeach
    </nav>
@endif
