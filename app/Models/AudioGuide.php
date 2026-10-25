<?php

namespace App\Models;

use Database\Factories\AudioGuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $artwork_id
 * @property string $locale
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property int|null $duration_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'path', 'mime_type', 'size', 'duration_seconds'])]
class AudioGuide extends Model
{
    /** @use HasFactory<AudioGuideFactory> */
    use HasFactory;

    /**
     * The disk the audio files are stored on. It is private, so files are served through a route.
     */
    public const DISK = 'local';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * Get the artwork the audio guide belongs to.
     *
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    /**
     * Get the absolute path of the audio file on the disk.
     */
    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->path);
    }

    /**
     * Delete the audio file, then the record.
     */
    public function deleteWithFile(): void
    {
        Storage::disk(self::DISK)->delete($this->path);

        $this->delete();
    }
}
