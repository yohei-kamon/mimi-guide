<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Give a model a permanent public slug of 12 lowercase letters and digits,
 * generated when the model is created and never changed afterwards.
 *
 * @mixin Model
 */
trait HasRandomSlug
{
    /**
     * Generate the slug when the model is created.
     */
    protected static function bootHasRandomSlug(): void
    {
        static::creating(function (self $model) {
            $model->slug ??= static::generateSlug();
        });
    }

    /**
     * Generate a random slug that is not used yet.
     */
    public static function generateSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(12));
        } while (static::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
