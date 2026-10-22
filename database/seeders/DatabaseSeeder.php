<?php

namespace Database\Seeders;

use App\Models\Artwork;
use App\Models\ArtworkTranslation;
use App\Models\Exhibition;
use App\Models\ExhibitionTranslation;
use App\Models\Museum;
use App\Models\MuseumTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $museum = Museum::factory()
            ->has(MuseumTranslation::factory()->state(['locale' => 'ja', 'name' => 'ミミ美術館']), 'translations')
            ->has(MuseumTranslation::factory()->state(['locale' => 'en', 'name' => 'Mimi Museum of Art']), 'translations')
            ->create([
                'slug' => 'mimi',
                'locales' => ['ja', 'en'],
                'default_locale' => 'ja',
            ]);

        $exhibitions = [
            [Exhibition::factory()->published(), '常設展 日本の名品', 'Permanent Collection'],
            [Exhibition::factory()->published()->state(['starts_on' => today()->subMonth(), 'ends_on' => today()->addMonths(2)]), '企画展 浮世絵の世界', 'The World of Ukiyo-e'],
            [Exhibition::factory(), '準備中 近代の版画', 'Modern Prints (draft)'],
            [Exhibition::factory()->published()->ended(), '企画展 茶の湯の美', 'The Art of Tea'],
        ];

        foreach ($exhibitions as [$factory, $jaName, $enName]) {
            $exhibition = $factory->for($museum)
                ->has(ExhibitionTranslation::factory()->locale('ja')->state(['name' => $jaName]), 'translations')
                ->has(ExhibitionTranslation::factory()->locale('en')->state(['name' => $enName]), 'translations')
                ->create();

            $this->seedArtworks($exhibition);
        }

        User::factory()->for($museum)->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
    }

    /**
     * Seed four artworks numbered from 1 in the exhibition; the last one is a draft.
     */
    private function seedArtworks(Exhibition $exhibition): void
    {
        foreach (range(1, 4) as $number) {
            $factory = $number === 4 ? Artwork::factory() : Artwork::factory()->published();

            $factory->for($exhibition)
                ->has(ArtworkTranslation::factory()->locale('ja'), 'translations')
                ->has(ArtworkTranslation::factory()->locale('en')->state(['artist' => null, 'production_year' => null]), 'translations')
                ->create(['guide_number' => $number, 'sort_order' => $number]);
        }
    }
}
