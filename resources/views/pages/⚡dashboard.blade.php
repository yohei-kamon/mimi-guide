<?php

use App\Enums\GuidePassStatus;
use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\Museum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ダッシュボード')] class extends Component {
    #[Locked]
    public Museum $museum;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Exhibition::class);

        $this->museum = Auth::user()->museum->load('translations');
    }

    /**
     * Get the number of the museum's exhibitions.
     */
    #[Computed]
    public function exhibitionCount(): int
    {
        return $this->museum->exhibitions()->count();
    }

    /**
     * Get the museum's artworks with their translations and audio, in exhibition and playback order.
     *
     * @return Collection<int, Artwork>
     */
    #[Computed]
    public function artworks(): Collection
    {
        return Artwork::query()
            ->whereHas('exhibition', fn ($query) => $query->where('museum_id', $this->museum->id))
            ->with(['exhibition.translations', 'translations', 'audioGuides'])
            ->orderBy('exhibition_id')
            ->ordered()
            ->get()
            ->each(fn (Artwork $artwork) => $artwork->exhibition->setRelation('museum', $this->museum))
            ->toBase();
    }

    /**
     * Get the artworks that lack a translation or audio in any of the museum's locales, with the missing locale names.
     *
     * @return Collection<int, array{artwork: Artwork, missingTranslations: list<string>, missingAudio: list<string>}>
     */
    #[Computed]
    public function incompleteArtworks(): Collection
    {
        $localeNames = config('mimi.locales');

        return $this->artworks
            ->map(fn (Artwork $artwork) => [
                'artwork' => $artwork,
                'missingTranslations' => $this->missingLocales($artwork->translations->pluck('locale')->all(), $localeNames),
                'missingAudio' => $this->missingLocales($artwork->audioGuides->pluck('locale')->all(), $localeNames),
            ])
            ->filter(fn (array $row) => $row['missingTranslations'] !== [] || $row['missingAudio'] !== [])
            ->values();
    }

    /**
     * Get the number of passes sold and their total amount in yen.
     *
     * @return array{count: int, total: int}
     */
    #[Computed]
    public function sales(): array
    {
        $paidPasses = $this->museum->guidePasses()->where('status', GuidePassStatus::Paid);

        return [
            'count' => (clone $paidPasses)->count(),
            'total' => (int) $paidPasses->sum('amount'),
        ];
    }

    /**
     * Get the names of the museum's locales that are not in the given list.
     *
     * @param  list<string>  $registeredLocales
     * @param  array<string, string>  $localeNames
     * @return list<string>
     */
    private function missingLocales(array $registeredLocales, array $localeNames): array
    {
        return collect($this->museum->locales)
            ->diff($registeredLocales)
            ->map(fn (string $locale) => $localeNames[$locale] ?? $locale)
            ->values()
            ->all();
    }
}; ?>

<section class="w-full space-y-8">
    <div>
        <flux:text>{{ $museum->name() }}</flux:text>
        <flux:heading size="xl" level="1" class="mt-1">ダッシュボード</flux:heading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>展示</flux:text>
            <div class="mt-2 text-3xl font-semibold tabular-nums text-zinc-800 dark:text-white">{{ number_format($this->exhibitionCount) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>作品</flux:text>
            <div class="mt-2 text-3xl font-semibold tabular-nums text-zinc-800 dark:text-white">{{ number_format($this->artworks->count()) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>ガイドパスの販売数</flux:text>
            <div class="mt-2 text-3xl font-semibold tabular-nums text-zinc-800 dark:text-white">{{ number_format($this->sales['count']) }}枚</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>売上合計</flux:text>
            <div class="mt-2 text-3xl font-semibold tabular-nums text-zinc-800 dark:text-white">{{ number_format($this->sales['total']) }}円</div>
        </div>
    </div>

    <div class="space-y-3">
        <flux:heading size="lg" level="2">音声や翻訳が未登録の作品</flux:heading>

        @if ($this->incompleteArtworks->isEmpty())
            <flux:text>全ての作品に、対応言語の翻訳と音声が登録されています。</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>展示</flux:table.column>
                    <flux:table.column>ガイド番号</flux:table.column>
                    <flux:table.column>作品名</flux:table.column>
                    <flux:table.column>未登録</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->incompleteArtworks as ['artwork' => $artwork, 'missingTranslations' => $missingTranslations, 'missingAudio' => $missingAudio])
                        <flux:table.row :key="$artwork->id">
                            <flux:table.cell>{{ $artwork->exhibition->name() }}</flux:table.cell>
                            <flux:table.cell>{{ $artwork->guide_number }}</flux:table.cell>
                            <flux:table.cell variant="strong">{{ $artwork->title() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($missingTranslations as $localeName)
                                        <flux:badge size="sm" color="amber">翻訳: {{ $localeName }}</flux:badge>
                                    @endforeach
                                    @foreach ($missingAudio as $localeName)
                                        <flux:badge size="sm" color="zinc">音声: {{ $localeName }}</flux:badge>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" icon="pencil-square" :href="route('artworks.edit', $artwork)" wire:navigate>
                                    編集
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</section>
