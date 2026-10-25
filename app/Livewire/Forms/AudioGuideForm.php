<?php

namespace App\Livewire\Forms;

use App\Models\Artwork;
use App\Models\AudioGuide;
use App\Services\TextToSpeechService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use RuntimeException;

class AudioGuideForm extends Form
{
    /**
     * The MIME type saved for each allowed extension.
     *
     * The `mimes` rule is not used because it guesses `mpga` for MP3 files.
     *
     * @var array<string, string>
     */
    private const MIME_TYPES = [
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
    ];

    public string $locale = '';

    public ?TemporaryUploadedFile $file = null;

    /**
     * Validate the form and add or replace the artwork's audio guide in the selected locale.
     */
    public function save(Artwork $artwork): AudioGuide
    {
        $this->validate($this->rulesFor($artwork), attributes: $this->fieldNames());

        $file = $this->file;
        $path = $file->store('audio/'.$artwork->exhibition->museum_id, AudioGuide::DISK);

        if ($path === false) {
            throw new RuntimeException('The audio file could not be stored.');
        }

        $audioGuide = $this->replaceAudioGuide(
            $artwork,
            $path,
            self::MIME_TYPES[strtolower($file->getClientOriginalExtension())],
            $file->getSize(),
        );

        $this->reset('file');

        return $audioGuide;
    }

    /**
     * Generate speech from the artwork's description in the selected locale and add or replace its audio guide.
     *
     * The description of that exact locale is used, without falling back to the default locale.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function generate(Artwork $artwork, TextToSpeechService $textToSpeech): ?AudioGuide
    {
        $this->validate(
            ['locale' => $this->rulesFor($artwork)['locale']],
            attributes: $this->fieldNames(),
        );

        $description = trim((string) $artwork->translations()->where('locale', $this->locale)->value('description'));

        if ($description === '') {
            $this->addError('locale', 'この言語の解説文が登録されていません。');

            return null;
        }

        $audio = $textToSpeech->synthesize($description, $this->locale);
        $path = 'audio/'.$artwork->exhibition->museum_id.'/'.Str::random(40).'.mp3';
        Storage::disk(AudioGuide::DISK)->put($path, $audio);

        return $this->replaceAudioGuide($artwork, $path, 'audio/mpeg', strlen($audio));
    }

    /**
     * Point the artwork's audio guide in the selected locale to the stored file, then delete the old file.
     */
    private function replaceAudioGuide(Artwork $artwork, string $path, string $mimeType, int $size): AudioGuide
    {
        $oldPath = $artwork->audioGuides()->where('locale', $this->locale)->value('path');

        $audioGuide = $artwork->audioGuides()->updateOrCreate(
            ['locale' => $this->locale],
            [
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'duration_seconds' => null,
            ],
        );

        if ($oldPath !== null) {
            Storage::disk(AudioGuide::DISK)->delete($oldPath);
        }

        return $audioGuide;
    }

    /**
     * Get the validation rules; the locale must be one the museum supports.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    private function rulesFor(Artwork $artwork): array
    {
        return [
            'locale' => ['required', 'string', Rule::in($artwork->exhibition->museum->locales)],
            'file' => [
                'required',
                'file',
                'extensions:'.implode(',', array_keys(self::MIME_TYPES)),
                'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a',
                'max:20480',
            ],
        ];
    }

    /**
     * Get the display names of the fields for error messages.
     *
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return [
            'locale' => '言語',
            'file' => '音声ファイル',
        ];
    }
}
