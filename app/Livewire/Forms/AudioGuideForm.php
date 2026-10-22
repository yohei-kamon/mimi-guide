<?php

namespace App\Livewire\Forms;

use App\Models\Artwork;
use App\Models\AudioGuide;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

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
        $oldPath = $artwork->audioGuides()->where('locale', $this->locale)->value('path');

        $audioGuide = $artwork->audioGuides()->updateOrCreate(
            ['locale' => $this->locale],
            [
                'path' => $path,
                'mime_type' => self::MIME_TYPES[strtolower($file->getClientOriginalExtension())],
                'size' => $file->getSize(),
                'duration_seconds' => null,
            ],
        );

        if ($oldPath !== null) {
            Storage::disk(AudioGuide::DISK)->delete($oldPath);
        }

        $this->reset('file');

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
