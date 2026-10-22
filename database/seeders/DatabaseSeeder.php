<?php

namespace Database\Seeders;

use App\Models\Museum;
use App\Models\MuseumTranslation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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

        User::factory()->for($museum)->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
    }
}
